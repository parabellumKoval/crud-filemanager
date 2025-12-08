<?php

namespace Backpack\FileManager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BackpackElfinderController extends \Barryvdh\Elfinder\ElfinderController
{
    public function showConnector()
    {
        $this->hydrateLegacySuperglobals(app(Request::class));

        return parent::showConnector();
    }

    public function showPopup($input_id)
    {
        $mimes = request('mimes');

        if (! isset($mimes)) {
            Log::error('Someone attempted to tamper with mime types in elfinder popup. The attempt was blocked.');
            abort(403, 'Unauthorized action.');
        }

        try {
            $mimes = Crypt::decrypt(urldecode(request('mimes')));
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::error('Someone attempted to tamper with mime types in elfinder popup. The attempt was blocked.');
            abort(403, 'Unauthorized action.');
        }

        if (! empty($mimes)) {
            request()->merge(['mimes' => urlencode(serialize($mimes))]);
        } else {
            request()->merge(['mimes' => '']);
        }

        return $this->app['view']
            ->make($this->package.'::standalonepopup')
            ->with($this->getViewVars())
            ->with(compact('input_id'));
    }

    /**
     * elFinder expects classic PHP superglobals, but Octane/Swoole does not populate them.
     */
    protected function hydrateLegacySuperglobals(Request $request): void
    {
        if (! isset($_SERVER) || ! is_array($_SERVER)) {
            $_SERVER = [];
        }

        $_SERVER['REQUEST_METHOD'] = strtoupper($request->getMethod() ?: 'GET');
        $_GET = $request->query->all();
        $_POST = $request->isMethod('GET') ? [] : $request->request->all();
        $_REQUEST = array_merge($_GET, $_POST);
        $_FILES = $this->formatUploadedFiles($request->files->all());
    }

    protected function formatUploadedFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $name => $file) {
            if ($file === null) {
                continue;
            }

            $normalized[$name] = $this->formatUploadedFileEntry($file);
        }

        return $normalized;
    }

    /**
     * Convert UploadedFile trees back to the structure PHP puts in $_FILES.
     *
     * @param  UploadedFile|array|null  $file
     */
    protected function formatUploadedFileEntry($file): array
    {
        if ($file instanceof UploadedFile) {
            return [
                'name' => $file->getClientOriginalName(),
                'type' => $file->getClientMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error' => $file->getError(),
                'size' => $file->getSize(),
            ];
        }

        if (is_array($file)) {
            $template = [
                'name' => [],
                'type' => [],
                'tmp_name' => [],
                'error' => [],
                'size' => [],
            ];

            foreach ($file as $key => $value) {
                if ($value === null) {
                    continue;
                }

                $entry = $this->formatUploadedFileEntry($value);

                foreach (array_keys($template) as $attribute) {
                    $template[$attribute][$key] = $entry[$attribute] ?? null;
                }
            }

            return $template;
        }

        return [
            'name' => null,
            'type' => null,
            'tmp_name' => null,
            'error' => \UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ];
    }
}
