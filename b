[33mcommit 08b6ec5bf93da086c399369b7c96d5d935cbe16e[m[33m ([m[1;36mHEAD[m[33m -> [m[1;32mmain[m[33m, [m[1;31morigin/main[m[33m)[m
Author: JUANCAMILO2019m <juancamilocalderon69@gmail.com>
Date:   Sat Sep 26 12:49:45 2026 -0500

    Confiar en proxy de Render para forzar HTTPS

[1mdiff --git a/app/Providers/AppServiceProvider.php b/app/Providers/AppServiceProvider.php[m
[1mindex 452e6b6..e67dd92 100644[m
[1m--- a/app/Providers/AppServiceProvider.php[m
[1m+++ b/app/Providers/AppServiceProvider.php[m
[36m@@ -3,6 +3,7 @@[m
 namespace App\Providers;[m
 [m
 use Illuminate\Support\ServiceProvider;[m
[32m+[m[32muse Illuminate\Support\Facades\URL;[m
 [m
 class AppServiceProvider extends ServiceProvider[m
 {[m
