<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class IdGeneratorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/IdGenerator', ['activePage' => 'id-generator']);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg', 'max:5120'],
            'nickname' => ['required', 'string', 'max:80'],
            'fullname' => ['required', 'string', 'max:120'],
            'position' => ['required', 'string', 'max:120'],
            'empnumber' => ['required', 'integer', 'min:1'],
            'fontsize' => ['required', 'integer', 'between:10,120'],
            'fontsize2' => ['required', 'integer', 'between:10,80'],
        ]);
        if (!extension_loaded('gd')) {
            return back()->withErrors(['form' => 'The PHP GD extension is required to generate ID cards. Enable GD in your PHP configuration and try again.']);
        }

        $directory = public_path('tools/sp-id-generator');
        $imagePath = $request->file('image')->getRealPath();
        $imageInfo = $imagePath ? getimagesize($imagePath) : false;
        if ($imageInfo === false || $imageInfo[2] !== IMAGETYPE_JPEG) {
            return back()->withErrors(['image' => 'Choose a valid JPEG photo.']);
        }

        $previousPost = $_POST;
        $previousFiles = $_FILES;
        $previousDirectory = getcwd();
        $_POST = array_merge($_POST, $validated);
        try {
            session_name('dabaw_helpdesk_tool');
            session_set_cookie_params([
                'httponly' => true,
                'secure' => $request->isSecure(),
                'samesite' => 'Lax',
                'path' => '/helpdesk',
            ]);
            session_start();
            session_regenerate_id(true);
            $_SESSION['user'] = $request->session()->get('user');
            $_SESSION['_id_generator_csrf'] = bin2hex(random_bytes(32));

            chdir($directory);
            if (!defined('IDGEN_GENERATE_REQUEST')) {
                define('IDGEN_GENERATE_REQUEST', true);
            }
            include $directory.'/idgen.php';
            $name = strtolower(preg_replace('#[^0-9a-zA-z.]#', '', ucwords($validated['fullname']))).'.png';
            if (!is_file($directory.'/cards/'.$name)) {
                throw new RuntimeException('The ID generator did not create an output card.');
            }
        } catch (\Throwable $exception) {
            Log::error('ID card generation failed.', ['exception' => $exception]);
            return back()->withErrors(['form' => 'The ID card could not be generated. Check the photo, bundled fonts, and PHP GD configuration.']);
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
            $_POST = $previousPost;
            $_FILES = $previousFiles;
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_name(config('session.cookie'));
        }

        return back()->with('generatedCard', $name)->with('success', 'ID card generated.');
    }

    public function download(string $file)
    {
        abort_unless(preg_match('/^[a-z0-9.]+\.png$/', $file), 404);
        $path = public_path('tools/sp-id-generator/cards/'.$file);
        abort_unless(is_file($path), 404);

        return response()->download($path, $file, ['Content-Type' => 'image/png']);
    }

    public function preview(string $file)
    {
        abort_unless(preg_match('/^[a-z0-9.]+\.png$/', $file), 404);
        $path = public_path('tools/sp-id-generator/cards/'.$file);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'image/png']);
    }
}
