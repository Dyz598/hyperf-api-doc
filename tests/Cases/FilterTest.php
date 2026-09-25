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

use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Filter\GroupFilter;
use HyperfApiDoc\Filter\TagFilter;
use HyperfApiDoc\Model\ApiDocument;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiParameter;
use HyperfApiDoc\Model\ApiResponse;
use HyperfApiDoc\Model\ApiSecurityScheme;

/**
 * @internal
 * @coversNothing
 */
class FilterTest extends AbstractTestCase
{
    public function testGroupFilterMatchesAssignedGroups()
    {
        $filter = new GroupFilter(['customer', 'admin']);

        $this->assertTrue($filter->matches((new ApiOperation())->group('customer')));
        $this->assertFalse($filter->matches((new ApiOperation())->group('partner')));
        $this->assertFalse($filter->matches(new ApiOperation()));
    }

    public function testGroupFilterMatchesAnyOfMultipleAssignedGroups()
    {
        $filter = new GroupFilter(['customer', 'admin']);
        $operation = (new ApiOperation())->group('partner', 'admin');

        $this->assertSame(['partner', 'admin'], $operation->groups);
        $this->assertTrue($filter->matches($operation));
        $this->assertTrue($filter->matches((new ApiOperation())->group('admin', 'partner')));
        $this->assertFalse((new GroupFilter(['partner']))->matches($operation->group('customer', 'internal')));
    }

    public function testGroupFilterMatchesUngroupedWhenNullIncluded()
    {
        $filter = new GroupFilter(['customer', null]);

        $this->assertTrue($filter->matches((new ApiOperation())->group('customer')));
        $this->assertTrue($filter->matches(new ApiOperation()));
    }

    public function testTagFilterMatchesAnyTag()
    {
        $filter = new TagFilter(['Savings Accounts', 'Loans']);

        $this->assertTrue($filter->matches((new ApiOperation())->tags('Loans')));
        $this->assertTrue($filter->matches((new ApiOperation())->tags('Posts', 'Loans')));
        $this->assertFalse($filter->matches((new ApiOperation())->tags('Posts')));
    }

    public function testDocumentFilteredAppliesAllFilters()
    {
        $document = new ApiDocument();
        $document->operation((new ApiOperation())->group('customer')->tags('Loans'));
        $document->operation((new ApiOperation())->group('admin')->tags('Loans'));
        $document->operation((new ApiOperation())->group('customer')->tags('Posts'));

        $filtered = $document->filtered([new GroupFilter(['customer']), new TagFilter(['Loans'])]);

        $this->assertCount(1, $filtered->operations);
        $this->assertSame(['customer'], $filtered->operations[0]->groups);
        $this->assertSame(['Loans'], $filtered->operations[0]->tags);
    }

    public function testDocumentFilteredWithoutFiltersReturnsSameInstance()
    {
        $document = new ApiDocument();

        $this->assertSame($document, $document->filtered([]));
    }

    public function testInvalidParameterLocationIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('invalid location [body]');

        new ApiParameter('x', 'body');
    }

    public function testInvalidResponseStatusIsRejected()
    {
        $operation = new ApiOperation();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid response status [999]');

        $operation->response(999);
    }

    public function testInvalidExtensionKeyIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('x- prefixed');

        (new ApiResponse(200))->extensions(['internal' => true]);
    }

    public function testInvalidSecuritySchemeNameIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Security scheme name [my scheme!] is invalid');

        new ApiSecurityScheme('my scheme!');
    }
}
