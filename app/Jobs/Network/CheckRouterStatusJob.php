<?php

namespace App\Jobs\Network;

use App\Actions\Router\CheckRouterConnection;
use App\Models\Router;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Menguji koneksi API router dan memperbarui status Online/Offline.
 * Tidak memakai kunci router: ping status boleh berjalan bersamaan dengan perintah lain.
 */
class CheckRouterStatusJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 20;

    /**
     * Cegah antrean menumpuk bila worker tertinggal dari jadwal 5 menitan.
     */
    public int $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(public Router $router) {}

    public function uniqueId(): string
    {
        return (string) $this->router->id;
    }

    /**
     * Execute the job.
     */
    public function handle(CheckRouterConnection $checkRouterConnection): void
    {
        $checkRouterConnection->handle($this->router);
    }
}
