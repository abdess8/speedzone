<?php

use Illuminate\Support\Facades\Storage;

test('uploaded public files are served even without the storage symlink', function () {
    Storage::fake('public');
    Storage::disk('public')->put('tests/hello.txt', 'uploaded-ok');

    $response = $this->get('/storage/tests/hello.txt');

    $response->assertOk();
    expect($response->streamedContent())->toBe('uploaded-ok');
});

test('the public storage route rejects path traversal', function () {
    $this->get('/storage/../.env')->assertNotFound();
    $this->get('/storage/foo/../../.env')->assertNotFound();
});

test('missing public files 404', function () {
    Storage::fake('public');

    $this->get('/storage/missing/file.pdf')->assertNotFound();
});
