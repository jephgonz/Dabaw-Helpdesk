<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert(['id' => 1, 'role' => 'admin', 'is_active' => 1]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('helpdesk_users');

        parent::tearDown();
    }

    public function test_admin_can_generate_preview_and_download_a_private_id_card(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagettftext')) {
            $this->markTestSkipped('PHP GD with FreeType support is required for ID generation.');
        }

        Storage::fake('local');
        $photoPath = tempnam(sys_get_temp_dir(), 'id-photo-');
        $photo = imagecreatetruecolor(450, 450);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 255, 255, 255));
        imagejpeg($photo, $photoPath);
        imagedestroy($photo);

        try {
            $response = $this->from('/admin/tools/id-generator')
                ->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
                ->post('/admin/tools/id-generator', [
                    'image' => UploadedFile::fake()->createWithContent('employee.jpg', File::get($photoPath)),
                    'nickname' => 'alex',
                    'fullname' => 'alex santos',
                    'position' => 'helpdesk officer',
                    'empnumber' => 42,
                    'fontsize' => 70,
                    'fontsize2' => 35,
                ]);
        } finally {
            unlink($photoPath);
        }

        $response->assertRedirect('/admin/tools/id-generator')
            ->assertSessionHas('success', 'ID card generated.');
        $fileName = session('generatedCard');
        $this->assertIsString($fileName);
        $this->assertMatchesRegularExpression('/^card-[a-f0-9]{32}\.png$/', $fileName);

        $path = 'id-generator/cards/'.$fileName;
        Storage::disk('local')->assertExists($path);

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->get('/admin/tools/id-generator/preview/'.$fileName)
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->get('/admin/tools/id-generator/download/'.$fileName)
            ->assertOk()
            ->assertDownload($fileName);
    }
}
