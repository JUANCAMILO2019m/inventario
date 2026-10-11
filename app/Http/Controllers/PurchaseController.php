<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
            'supplier' => 'nullable|string|max:20',
            'status'   => 'nullable|in:active,voided',
        ]);

        $query = Purchase::query()
            ->when($request->filled('supplier'), function ($q) use ($request) {
                $request->input('supplier') === 'none'
                    ? $q->whereNull('supplier_id')
                    : $q->where('supplier_id', $request->input('supplier'));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('purchased_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('purchased_at', '<=', $request->input('to')))
            ->when(
                $request->input('status') === 'voided',
                fn ($q) => $q->whereNotNull('voided_at'),
                fn ($q) => $q->whereNull('voided_at')
            );

        $total = (float) (clone $query)->sum('total');

        $purchases = $query->with(['supplier', 'user'])
            ->withCount('items')
            ->latest('purchased_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        return view('purchases.index', compact('purchases', 'suppliers', 'total'));
    }

    public function create()
    {
        return view('purchases.create', [
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'products'  => Product::orderBy('name')->get(['id', 'name', 'unit', 'package_name', 'package_size', 'price', 'quantity']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id'          => 'nullable|exists:suppliers,id',
            'purchased_at'         => 'required|date|before_or_equal:today',
            'invoice_number'       => 'nullable|string|max:100',
            'notes'                => 'nullable|string|max:1000',
            'items'                => 'required|array|min:1|max:50',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.mode'         => 'required|in:package,unit',
            'items.*.qty'          => 'required|numeric|gt:0|decimal:0,3',
            'items.*.total'        => 'required|numeric|min:0|decimal:0,2',
            'items.*.expires_on'   => 'nullable|date|after_or_equal:purchased_at',
            'items.*.lot_code'     => 'nullable|string|max:100',
        ], [
            'items.required'                    => 'Agrega al menos un producto.',
            'items.*.product_id.required'       => 'Línea :position: elige el producto.',
            'items.*.qty.required'              => 'Línea :position: indica la cantidad.',
            'items.*.qty.gt'                    => 'Línea :position: la cantidad debe ser mayor que 0.',
            'items.*.total.required'            => 'Línea :position: indica el total de la línea.',
            'items.*.expires_on.after_or_equal' => 'Línea :position: el vencimiento no puede ser anterior a la fecha de compra.',
        ]);

        $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))->get()->keyBy('id');

        $lines = [];
        $problems = [];

        foreach (array_values($data['items']) as $i => $item) {
            $n = $i + 1;
            $product = $products[$item['product_id']];
            $qty = (float) $item['qty'];

            if ($item['mode'] === 'package') {
                if ((float) $product->package_size <= 0) {
                    $problems[] = "Línea {$n}: «{$product->name}» no tiene empaque definido. Edita el producto o compra por unidad.";
                    continue;
                }

                $baseQty = round($qty * (float) $product->package_size, 3);
            } else {
                $baseQty = round($qty, 3);
            }

            if ($baseQty <= 0) {
                $problems[] = "Línea {$n}: la cantidad es demasiado pequeña.";
                continue;
            }

            $total = round((float) $item['total'], 2);

            $lines[] = [
                'product'    => $product,
                'mode'       => $item['mode'],
                'qty'        => $qty,
                'base_qty'   => $baseQty,
                'total'      => $total,
                'unit_cost'  => round($total / $baseQty, 4),
                'expires_on' => $item['expires_on'] ?? null,
                'lot_code'   => $item['lot_code'] ?? null,
            ];
        }

        if ($problems) {
            return back()->withInput()->withErrors($problems);
        }

        $purchase = DB::transaction(function () use ($data, $lines) {
            $purchase = Purchase::create([
                'supplier_id'    => $data['supplier_id'] ?? null,
                'purchased_at'   => $data['purchased_at'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'total'          => round(array_sum(array_column($lines, 'total')), 2),
            ]);

            $supplierName = $purchase->supplier?->name;
            $reason = Str::limit(
                "Compra #{$purchase->id}"
                    . ($supplierName ? " · {$supplierName}" : '')
                    . ($purchase->invoice_number ? " · Fact. {$purchase->invoice_number}" : ''),
                250,
                ''
            );

            foreach ($lines as $line) {
                $product = $line['product'];
                $isPackage = $line['mode'] === 'package';

                [$movement, $avgBefore, $avgAfter] = $product->receivePurchase(
                    $line['base_qty'],
                    $line['unit_cost'],
                    $reason
                );

                $item = $purchase->items()->create([
                    'product_id'        => $product->id,
                    'product_name'      => $product->name,
                    'unit'              => $product->unit,
                    'packages'          => $isPackage ? $line['qty'] : null,
                    'package_size'      => $isPackage ? $product->package_size : null,
                    'package_name'      => $isPackage ? $product->package_name : null,
                    'quantity'          => $line['base_qty'],
                    'line_total'        => $line['total'],
                    'unit_cost'         => $line['unit_cost'],
                    'avg_before'        => $avgBefore,
                    'avg_after'         => $avgAfter,
                    'stock_movement_id' => $movement->id,
                ]);

                if ($line['expires_on']) {
                    ProductBatch::create([
                        'product_id'       => $product->id,
                        'purchase_item_id' => $item->id,
                        'lot_code'         => $line['lot_code'],
                        'quantity'         => $line['base_qty'],
                        'expires_on'       => $line['expires_on'],
                    ]);
                }
            }

            return $purchase;
        });

        return redirect()->route('purchases.show', $purchase)
            ->with('success', 'Compra registrada: el stock y el costo promedio se actualizaron.');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'user', 'items.product', 'items.batch']);

        // Costo de la compra anterior del mismo producto, para ver la variación
        $previous = [];

        foreach ($purchase->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            $previous[$item->id] = PurchaseItem::where('product_id', $item->product_id)
                ->where('id', '<', $item->id)
                ->whereHas('purchase', fn ($q) => $q->whereNull('voided_at'))
                ->latest('id')
                ->value('unit_cost');
        }

        return view('purchases.show', compact('purchase', 'previous'));
    }

    public function cancel(Purchase $purchase)
    {
        $notices = [];

        try {
            DB::transaction(function () use ($purchase, &$notices) {
                $locked = Purchase::lockForUpdate()->findOrFail($purchase->id);

                if ($locked->isVoided()) {
                    throw new \InvalidArgumentException('Esta compra ya está anulada.');
                }

                foreach ($locked->items()->with('product')->orderByDesc('id')->get() as $item) {
                    $product = $item->product;

                    if ($product) {
                        $product->refresh();

                        if ((float) $product->quantity + 0.0005 < (float) $item->quantity) {
                            throw new \InvalidArgumentException(
                                "No se puede anular: de «{$product->name}» ya se consumió parte de lo comprado "
                                . "(stock actual {$product->quantity} {$product->unit}, comprado {$item->quantity})."
                            );
                        }

                        $product->registerMovement(
                            'out',
                            (float) $item->quantity,
                            "Anulación de compra #{$locked->id}",
                            'reversal',
                            (float) $item->unit_cost
                        );

                        $product->refresh();

                        // El costo promedio solo se restaura si nadie lo cambió después de esta compra
                        if ($item->avg_after !== null && abs((float) $product->price - $item->avg_after) < 0.005) {
                            $product->update(['price' => $item->avg_before]);
                        } else {
                            $notices[] = $product->name;
                        }
                    }

                    ProductBatch::where('purchase_item_id', $item->id)->delete();
                }

                $locked->update(['voided_at' => now()]);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = 'Compra anulada: se restó lo comprado del stock.';

        if ($notices) {
            $message .= ' El costo promedio de ' . implode(', ', $notices)
                . ' no se modificó porque cambió después de esta compra.';
        }

        return redirect()->route('purchases.show', $purchase)->with('success', $message);
    }
}