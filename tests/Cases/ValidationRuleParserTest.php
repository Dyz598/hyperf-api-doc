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

use Hyperf\Validation\Rules\Enum;
use HyperfApiDoc\Scanner\EnumRuleDetector;
use HyperfApiDoc\Scanner\ValidationRuleParser;
use HyperfTest\Fixtures\Constant\Gender;
use HyperfTest\Fixtures\Constant\UserStatus;

/**
 * @internal
 * @coversNothing
 */
class ValidationRuleParserTest extends AbstractTestCase
{
    public function testParsesScalarTypesFormatsAndBounds()
    {
        $schema = (new ValidationRuleParser())->parse([
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'age' => 'required|integer|min:18|max:120',
            'balance' => 'required|numeric|min:0.5|max:99.99',
            'active' => 'boolean',
            'bio' => 'nullable|string',
            'code' => 'required|string|size:5',
            'birthday' => 'date_format:Y-m-d',
            'seen_at' => 'date_format:Y-m-d H:i:s',
        ]);

        $name = $schema->properties['name'];
        $this->assertSame('string', $name->type);
        $this->assertSame(100, $name->maxLength);
        $this->assertNull($name->nullable);
        $this->assertContains('name', $schema->required);

        $this->assertSame('string', $schema->properties['email']->type);
        $this->assertSame('email', $schema->properties['email']->format);

        $age = $schema->properties['age'];
        $this->assertSame('integer', $age->type);
        $this->assertSame(18, $age->minimum);
        $this->assertSame(120, $age->maximum);

        $balance = $schema->properties['balance'];
        $this->assertSame('number', $balance->type);
        $this->assertSame(0.5, $balance->minimum);
        $this->assertSame(99.99, $balance->maximum);

        $this->assertSame('boolean', $schema->properties['active']->type);
        $this->assertNotContains('active', $schema->required);

        $bio = $schema->properties['bio'];
        $this->assertTrue($bio->nullable);
        $this->assertNotContains('bio', $schema->required);

        $code = $schema->properties['code'];
        $this->assertSame(5, $code->minLength);
        $this->assertSame(5, $code->maxLength);

        $this->assertSame('date', $schema->properties['birthday']->format);
        $this->assertSame('date-time', $schema->properties['seen_at']->format);
    }

    public function testRuleOrderDoesNotMatterForBounds()
    {
        $schema = (new ValidationRuleParser())->parse([
            'name' => 'max:100|string|required',
        ]);

        $this->assertSame('string', $schema->properties['name']->type);
        $this->assertSame(100, $schema->properties['name']->maxLength);
    }

    public function testParsesEnumRuleObject()
    {
        $schema = (new ValidationRuleParser())->parse([
            'status' => ['required', new Enum(UserStatus::class)],
        ]);

        $status = $schema->properties['status'];
        $this->assertSame('string', $status->type);
        $this->assertSame(['active', 'inactive', 'suspended'], $status->enum);
        $this->assertSame(UserStatus::class, $status->enumClass);
        $this->assertContains('status', $schema->required);
    }

    public function testParsesCustomEnumRulesFromConfig()
    {
        $schema = (new ValidationRuleParser([new EnumRuleDetector(['in_gender' => Gender::class])]))->parse([
            'gender' => 'required|in_gender',
        ]);

        $gender = $schema->properties['gender'];
        $this->assertSame('string', $gender->type);
        $this->assertSame(['male', 'female'], $gender->enum);
        $this->assertSame(Gender::class, $gender->enumClass);
    }

    public function testParsesInList()
    {
        $schema = (new ValidationRuleParser())->parse([
            'role' => 'required|in:admin,staff',
            'level' => 'required|integer|in:1,2,3',
        ]);

        $this->assertSame(['admin', 'staff'], $schema->properties['role']->enum);
        $this->assertSame('string', $schema->properties['role']->type);

        $level = $schema->properties['level'];
        $this->assertSame([1, 2, 3], $level->enum);
        $this->assertSame('integer', $level->type);
    }

    public function testParsesNestedObjects()
    {
        $schema = (new ValidationRuleParser())->parse([
            'address.city' => 'required|string',
            'address.postal_code' => 'required|string|size:5',
        ]);

        $address = $schema->properties['address'];
        $this->assertSame('object', $address->type);
        $this->assertSame('string', $address->properties['city']->type);
        $this->assertSame(5, $address->properties['postal_code']->maxLength);
        $this->assertSame(['city', 'postal_code'], $address->required);
    }

    public function testParsesArrayOfScalars()
    {
        $schema = (new ValidationRuleParser())->parse([
            'tags.*' => 'required|string|max:20',
        ]);

        $tags = $schema->properties['tags'];
        $this->assertSame('array', $tags->type);
        $this->assertSame('string', $tags->items->type);
        $this->assertSame(20, $tags->items->maxLength);
    }

    public function testParsesArrayOfObjects()
    {
        $schema = (new ValidationRuleParser())->parse([
            'schedules.*.due_date' => 'required|date_format:Y-m-d',
            'schedules.*.amount' => 'required|numeric',
        ]);

        $schedules = $schema->properties['schedules'];
        $this->assertSame('array', $schedules->type);
        $this->assertSame('object', $schedules->items->type);

        $dueDate = $schedules->items->properties['due_date'];
        $this->assertSame('string', $dueDate->type);
        $this->assertSame('date', $dueDate->format);

        $this->assertSame(['due_date', 'amount'], $schedules->items->required);
    }

    public function testParsesFileUploadsAsBinary()
    {
        $schema = (new ValidationRuleParser())->parse([
            'avatar' => 'required|image',
            'document' => 'required|mimes:pdf,docx',
        ]);

        $this->assertSame('string', $schema->properties['avatar']->type);
        $this->assertSame('binary', $schema->properties['avatar']->format);
        $this->assertSame('binary', $schema->properties['document']->format);
    }

    public function testParsesRegexPatternWithoutDelimiters()
    {
        $schema = (new ValidationRuleParser())->parse([
            'slug' => 'required|string|regex:/^[a-z0-9-]+$/',
        ]);

        $this->assertSame('^[a-z0-9-]+$', $schema->properties['slug']->pattern);
    }

    public function testNullableMarksPropertyNullable()
    {
        $schema = (new ValidationRuleParser())->parse([
            'nickname' => 'nullable|string',
        ]);

        $this->assertTrue($schema->properties['nickname']->nullable);
        $this->assertNotContains('nickname', $schema->required);
    }
}
