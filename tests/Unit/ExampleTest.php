<?php

declare(strict_types=1);

test('that a generated name is not empty', function (): void {
    expect(fake()->name())->not->toBeEmpty();
});
