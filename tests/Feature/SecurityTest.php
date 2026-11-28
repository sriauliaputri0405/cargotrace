<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\CreatesCargoFixtures;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase, CreatesCargoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createFixtures();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    /**
     * Menyapu seluruh route: kecuali daftar publik di bawah, tamu harus ditolak.
     * Kalau suatu saat sengaja membuat route publik baru, tambahkan di daftar ini dengan sadar.
     */
    public function test_every_protected_route_rejects_guests(): void
    {
        $publicExact = [
            '/', 'login', 'register', 'forgot-password', 'reset-password', 'reset-password/{token}',
            'up', 'api/login', 'api/register',
        ];
        $publicPrefix = ['storage/', 'sanctum/'];

        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (in_array($uri, $publicExact, true)) {
                continue;
            }

            foreach ($publicPrefix as $prefix) {
                if (str_starts_with($uri, $prefix)) {
                    continue 2;
                }
            }

            $url = '/' . ltrim((string) preg_replace('/\{[^}]+\}/', '1', $uri), '/');

            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $response = $this->call($method, $url);

                if (str_starts_with($uri, 'api/')) {
                    $this->assertSame(401, $response->getStatusCode(), "{$method} {$uri} harus 401 untuk tamu");
                } else {
                    $this->assertTrue(
                        $response->isRedirect(route('login')),
                        "{$method} {$uri} harus mengarahkan tamu ke login"
                    );
                }

                $checked++;
            }
        }

        $this->assertGreaterThan(30, $checked, 'Penyapuan route seharusnya memeriksa lebih dari 30 route.');
    }

    public function test_page_access_matrix_by_role(): void
    {
        $all = ['admin', 'operator', 'customer'];
        $staff = ['admin', 'operator'];

        $matrix = [
            '/dashboard'     => $all,
            '/shipments'     => $all,
            '/tracking'      => $all,
            '/notifications' => $all,
            '/profile'       => $all,
            '/exceptions'    => $staff,
            '/reports'       => $staff,
            '/carriers'      => ['admin'],
            '/locations'     => ['admin'],
            '/users'         => ['admin'],
            '/audit-logs'    => ['admin'],
        ];

        foreach ($all as $role) {
            $user = $this->makeUser($role);

            foreach ($matrix as $path => $allowed) {
                $expected = in_array($role, $allowed, true) ? 200 : 403;
                $actual = $this->actingAs($user)->get($path)->getStatusCode();

                $this->assertSame($expected, $actual, "{$role} membuka {$path}: seharusnya {$expected}, didapat {$actual}");
            }
        }
    }

    public function test_user_supplied_text_is_escaped_in_html(): void
    {
        Location::create([
            'name' => '<script>alert("x")</script>', 'city' => 'City', 'country' => 'ID',
            'type' => 'port', 'latitude' => 1, 'longitude' => 1,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->get('/locations')
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_malicious_sort_parameters_are_ignored(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/shipments?sort=id;drop table users&direction=asc')->assertOk();
        $this->actingAs($admin)->get('/shipments?sort=password&direction=sideways')->assertOk();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_customer_cannot_set_protected_fields_through_the_form(): void
    {
        $customer = $this->makeUser('customer');
        $other = $this->makeUser('customer');

        $this->actingAs($customer)->post('/shipments', $this->shipmentPayload([
            'status'           => 'DELIVERED',
            'is_delayed'       => 1,
            'delay_hours'      => 99,
            'created_by'       => $other->id,
            'customer_id'      => $other->id,
            'shipment_number'  => 'HACK-1',
            'exchange_rate'    => 99999,
            'freight_cost_idr' => 1,
        ]))->assertSessionHasNoErrors();

        $shipment = Shipment::latest('id')->firstOrFail();

        $this->assertSame('CREATED', $shipment->status);
        $this->assertFalse((bool) $shipment->is_delayed);
        $this->assertSame(0, (int) $shipment->delay_hours);
        $this->assertSame($customer->id, $shipment->created_by);
        $this->assertSame($customer->id, $shipment->customer_id);
        $this->assertStringStartsWith('SHP-', $shipment->shipment_number);
        $this->assertNull($shipment->exchange_rate);
        $this->assertNull($shipment->freight_cost_idr);
    }

    public function test_api_is_rate_limited(): void
    {
        Sanctum::actingAs($this->makeUser('customer'));

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/user')->assertOk();
        }

        $this->getJson('/api/user')->assertStatus(429);
    }

    public function test_api_errors_do_not_leak_internal_details(): void
    {
        Route::middleware('api')->get('api/_boom', function () {
            throw new RuntimeException('secret-database-password');
        });

        $this->getJson('/api/_boom')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Server error.']);
    }

    public function test_seeder_refuses_the_default_password_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['cargotrace.seed_password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_PASSWORD');

        // Dipanggil langsung: perintah "db:seed" punya konfirmasi sendiri di production
        app(DatabaseSeeder::class)->run();
    }

    public function test_seeder_uses_the_configured_password(): void
    {
        config(['cargotrace.seed_password' => 'a-strong-demo-pass-1']);

        app(DatabaseSeeder::class)->run();

        $admin = User::where('email', 'admin@cargotrace.test')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('a-strong-demo-pass-1', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
    }
}