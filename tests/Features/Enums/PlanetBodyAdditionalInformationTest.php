<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\PlanetBody;

// The descriptions were written in backticks, PHP's shell-execution operator: asking for one ran its
// prose as a shell command and returned that command's output instead of the text.
it('returns the description text itself, not the output of running it as a shell command', function (PlanetBody $body, string $opening) {
    expect($body->getAdditionalInformation())->toBeString()->toStartWith($opening);
})->with([
    'Harrington' => [PlanetBody::HARRINGTON, "This is another attempt to predict Planet X's orbit"],
    'Nibiru' => [PlanetBody::NIBIRU, 'A highly speculative planet derived from the theory of Zecharia Sitchin'],
    'Vulcan' => [PlanetBody::VULCAN, 'This is a ‘hypothetical’ planet inside the orbit of Mercury'],
    'Waldemath' => [PlanetBody::WALDEMATH, 'This is another hypothetical second Moon of the Earth'],
]);

it('has no description for Selena rather than a copy of Vulcan\'s', function () {
    expect(PlanetBody::SELENA->getAdditionalInformation())->toBeNull();
});

it('keeps each description on one line of prose', function () {
    expect(PlanetBody::HARRINGTON->getAdditionalInformation())->not->toContain("\n")->not->toContain('  ');
});
