<?php

namespace App\Http\Controllers;

use App\Services\IdCardGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class IdGeneratorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/IdGenerator', ['activePage' => 'id-generator']);
    }

    public function generate(Request $request, IdCardGenerator $generator): RedirectResponse
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
        if (! extension_loaded('gd') || ! function_exists('imagettftext')) {
            return back()->withErrors(['form' => 'The PHP GD extension with FreeType support is required to generate ID cards. Enable it in your PHP configuration and try again.']);
        }

        try {
            $name = $generator->generate(
                $request->file('image'),
                $validated['nickname'],
                $validated['fullname'],
                $validated['position'],
                (string) $validated['empnumber'],
                (int) $validated['fontsize'],
                (int) $validated['fontsize2'],
            );
        } catch (Throwable $exception) {
            Log::error('ID card generation failed.', ['exception' => $exception]);

            return back()->withErrors(['form' => 'The ID card could not be generated. Check the photo, bundled fonts, and PHP GD configuration.']);
        }

        return back()->with('generatedCard', $name)->with('success', 'ID card generated.');
    }

    public function download(string $file)
    {
        abort_unless(preg_match('/^card-[a-f0-9]{32}\.png$/', $file), 404);
        $path = 'id-generator/cards/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $file, ['Content-Type' => 'image/png']);
    }

    public function preview(string $file)
    {
        abort_unless(preg_match('/^card-[a-f0-9]{32}\.png$/', $file), 404);
        $path = 'id-generator/cards/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $file, ['Content-Type' => 'image/png']);
    }
}
