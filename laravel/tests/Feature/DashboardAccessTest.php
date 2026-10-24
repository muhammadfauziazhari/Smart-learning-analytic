<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(string $role): User
    {
        return User::create([
            'nama' => 'User '.$role,
            'email' => $role.'@example.com',
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    public static function roleProvider(): array
    {
        return [
            'admin' => ['admin', '/admin/dashboard'],
            'kaprodi' => ['kaprodi', '/kaprodi/dashboard'],
            'dosen' => ['dosen', '/dosen/dashboard'],
        ];
    }

    #[DataProvider('roleProvider')]
    public function test_user_can_login_and_open_own_dashboard(string $role, string $uri): void
    {
        $user = $this->createUser($role);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect($uri);

        $this->actingAs($user)->get($uri)->assertOk();
    }

    public function test_admin_dashboard_renders_admin_view(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewIs('Admin.AdminDashboard')
            ->assertSee('id="sidebar"', escape: false)
            ->assertSee($admin->nama)
            ->assertSee('data-target="dashboard"', escape: false)
            ->assertSee('data-target="uploadlog"', escape: false)
            ->assertSee('data-title="Upload Log"', escape: false)
            ->assertSee('id="content-uploadlog"', escape: false)
            ->assertSee('name="file_csv"', escape: false)
            // Directive component sudah dikompilasi, tidak boleh bocor ke HTML
            ->assertDontSee('<x-admin.sidebar', escape: false);
    }

    public function test_sidebar_component_marks_active_menu(): void
    {
        $admin = $this->createUser('admin');
        $this->actingAs($admin);

        $html = Blade::render('<x-admin.sidebar active="uploadlog" />');

        // Menu aktif memakai gaya highlighted
        $this->assertMatchesRegularExpression('/data-target="uploadlog"[^>]*class="[^"]*\bbg-indigo-600\b/', $html);

        // Menu lain tetap memakai gaya default
        $this->assertMatchesRegularExpression('/data-target="dashboard"[^>]*class="[^"]*\btext-slate-400\b/', $html);

        // Identitas user ikut tampil di footer sidebar
        $this->assertStringContainsString($admin->nama, $html);
    }

    public function test_kaprodi_dashboard_renders_kaprodi_view(): void
    {
        $user = $this->createUser('kaprodi');

        $this->actingAs($user)
            ->get('/kaprodi/dashboard')
            ->assertOk()
            ->assertViewIs('Kaprodi.Dashboard');
    }

    public function test_dosen_dashboard_renders_dosen_view(): void
    {
        $user = $this->createUser('dosen');

        $this->actingAs($user)
            ->get('/dosen/dashboard')
            ->assertOk()
            ->assertViewIs('Dosen.Dashboard');
    }

    #[DataProvider('dashboardProvider')]
    public function test_every_sidebar_menu_has_matching_tab_content(string $role, string $uri): void
    {
        $user = $this->createUser($role);
        $html = $this->actingAs($user)->get($uri)->assertOk()->getContent();

        $menuIds = $this->menuIdsFrom($html);
        $tabIds = $this->tabIdsFrom($html);

        $this->assertNotEmpty($menuIds, 'Sidebar tidak memiliki menu sama sekali.');

        // Menu dan tab harus sama persis, urut dan jumlahnya
        $this->assertSame($menuIds, $tabIds);

        // Hanya tab pertama yang terlihat saat halaman dibuka
        $this->assertStringContainsString('id="content-'.$menuIds[0].'" class="tab-content', $html);
        foreach (array_slice($menuIds, 1) as $tabId) {
            $this->assertStringContainsString('id="content-'.$tabId.'" class="tab-content space-y-6 hidden', $html);
        }
    }

    public static function dashboardProvider(): array
    {
        return [
            'admin' => ['admin', '/admin/dashboard'],
            'kaprodi' => ['kaprodi', '/kaprodi/dashboard'],
            'dosen' => ['dosen', '/dosen/dashboard'],
        ];
    }

    public function test_dosen_and_kaprodi_sidebar_have_five_menus(): void
    {
        $expected = ['dashboard', 'pengumpulan-tugas', 'keaktifan', 'ews', 'settings'];

        $dosen = $this->createUser('dosen');
        $dosenHtml = $this->actingAs($dosen)->get('/dosen/dashboard')->assertOk()->getContent();
        $this->assertSame($expected, $this->menuIdsFrom($dosenHtml));

        $kaprodi = $this->createUser('kaprodi');
        $kaprodiHtml = $this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertOk()->getContent();
        $this->assertSame($expected, $this->menuIdsFrom($kaprodiHtml));
    }

    /** Ambil id menu dari sidebar (bagian sebelum <main>). */
    private function menuIdsFrom(string $html): array
    {
        preg_match_all('/data-target="([^"]+)"/', $this->sidebarHtml($html), $matches);

        return $matches[1];
    }

    /** Ambil id tab konten dari seluruh halaman. */
    private function tabIdsFrom(string $html): array
    {
        preg_match_all('/id="content-([^"]+)"/', $html, $matches);

        return $matches[1];
    }

    private function sidebarHtml(string $html): string
    {
        return substr($html, 0, strpos($html, '<main'));
    }

    public function test_user_cannot_open_another_role_dashboard(): void
    {
        $dosen = $this->createUser('dosen');

        $this->actingAs($dosen)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }
}
