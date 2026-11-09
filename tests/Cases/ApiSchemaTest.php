<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\Cases;

use HyperfApiDoc\Model\ApiRequestBody;
use HyperfApiDoc\Model\ApiSchema;
use HyperfTest\Fixtures\Constant\Gender;

/**
 * @internal
 * @coversNothing
 */
class ApiSchemaTest extends AbstractTestCase
{
    public function testDottedDescriptionsNestInsteadOfFlattening()
    {
        $schema = new ApiSchema();
        $schema->descriptions([
            'user.name' => 'Display name.',
            'business' => 'The draft.',
        ]);

        $this->assertSame(['user', 'business'], array_keys($schema->properties));
        $this->assertSame('object', $schema->properties['user']->type);
        $this->assertSame('Display name.', $schema->properties['user']->properties['name']->description);
    }

    public function testDottedFieldExamplesAndFieldsPatchNestedLeaves()
    {
        $schema = new ApiSchema();
        $schema->fieldExamples(['business.name' => 'Warung Budi']);
        $schema->fields(['business.geo_lat' => ['type' => 'number', 'minimum' => -90]]);

        $name = $schema->properties['business']->properties['name'];
        $this->assertTrue($name->hasExample);
        $this->assertSame('Warung Budi', $name->example);

        $geoLat = $schema->properties['business']->properties['geo_lat'];
        $this->assertSame('number', $geoLat->type);
        $this->assertSame(-90, $geoLat->minimum);
    }

    public function testDottedEnumsPatchNestedLeaves()
    {
        $schema = new ApiSchema();
        $schema->enums(['user.gender' => Gender::class]);

        $gender = $schema->properties['user']->properties['gender'];
        $this->assertSame(
            array_map(static fn (Gender $gender) => $gender->value, Gender::cases()),
            $gender->enum
        );
        $this->assertSame(Gender::class, $gender->enumClass);
    }

    public function testWildcardPathAddressesArrayItems()
    {
        $schema = new ApiSchema();
        $schema->descriptions(['business.photos.*' => 'Photo file id.']);

        $photos = $schema->properties['business']->properties['photos'];
        $this->assertSame('array', $photos->type);
        $this->assertSame('Photo file id.', $photos->items->description);
    }

    public function testDottedRequireMarksEachLevel()
    {
        $schema = new ApiSchema();
        $schema->descriptions(['business.name' => 'Business name.']);
        $schema->require('business.name');

        $this->assertSame(['business'], $schema->required);
        $this->assertSame(['name'], $schema->properties['business']->required);
    }

    public function testRequireCascadesOnlyThroughExistingProperties()
    {
        $schema = new ApiSchema();
        $schema->require('ghost.name');

        $this->assertSame(['ghost'], $schema->required);
        $this->assertSame([], $schema->properties);
    }

    public function testFlatNamesKeepWorking()
    {
        $schema = new ApiSchema();
        $schema->descriptions(['name' => 'Flat.'])->require('name', 'name');

        $this->assertSame('Flat.', $schema->properties['name']->description);
        $this->assertSame(['name'], $schema->required);
    }

    public function testRequestBodyOverlayDelegatesDotPaths()
    {
        $body = new ApiRequestBody();
        $body->descriptions(['user.name' => 'Display name.']);

        $overlay = $body->overlay();
        $this->assertSame(['user'], array_keys($overlay->properties));
        $this->assertSame('Display name.', $overlay->properties['user']->properties['name']->description);
    }
}
