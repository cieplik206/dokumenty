<?php

test('media library config is available', function () {
    expect(config('media-library.disk_name'))->toBe('private')
        ->and(config('media-library.queue_connection_name'))->toBe('sync');
});
