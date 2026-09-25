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
use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Generator\SchemaResolver;
use HyperfApiDoc\Scanner\EnumRuleDetector;
use HyperfTest\Fixtures\Constant\Gender;
use HyperfTest\Fixtures\Constant\UserStatus;
use HyperfTest\Fixtures\Rule\InEnumRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class DetectorTest extends TestCase
{
    public function testDetectsRuleEnumObjects()
    {
        $facts = (new EnumRuleDetector())->detect(new Enum(UserStatus::class));

        $this->assertNotNull($facts);
        $this->assertSame(UserStatus::class, $facts->enumClass);
    }

    public function testDetectsBackedEnumInstances()
    {
        $facts = (new EnumRuleDetector())->detect(Gender::FEMALE);

        $this->assertNotNull($facts);
        $this->assertSame(Gender::class, $facts->enumClass);
    }

    public function testDetectsEnumClassStringsOnCustomRuleObjects()
    {
        $facts = (new EnumRuleDetector())->detect(new InEnumRule());

        $this->assertNotNull($facts);
        $this->assertSame(Gender::class, $facts->enumClass);
    }

    public function testDetectsEnumInstancesOnCustomRuleObjects()
    {
        $facts = (new EnumRuleDetector())->detect(new EnumHolder(Gender::MALE));

        $this->assertNotNull($facts);
        $this->assertSame(Gender::class, $facts->enumClass);
    }

    public function testDetectsCaseListsOnCustomRuleObjects()
    {
        $facts = (new EnumRuleDetector())->detect(new EnumHolder([Gender::MALE, Gender::FEMALE]));

        $this->assertNotNull($facts);
        $this->assertSame(['male', 'female'], $facts->enum);
    }

    public function testIgnoresRulesWithoutEnums()
    {
        $detector = new EnumRuleDetector();

        $this->assertNull($detector->detect(new EnumHolder('not-an-enum')));
        $this->assertNull($detector->detect('in_gender'));
    }

    public function testMapResolvesStringRules()
    {
        $detector = new EnumRuleDetector(['in_gender' => Gender::class]);

        $this->assertSame(Gender::class, $detector->detect('in_gender')?->enumClass);
        $this->assertNull($detector->detect('unknown_rule'));
    }

    public function testInvalidDetectorConfigIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Rule detector class [Not\A\Detector] does not exist.');

        new SchemaResolver(['rule_detectors' => ['Not\A\Detector']]);
    }
}

final class EnumHolder
{
    public function __construct(
        public readonly mixed $value = null,
    ) {}
}
