<?php

/*
 * This file is part of the "news" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace GeorgRinger\News\Tests\Functional\Pagination;

use GeorgRinger\News\Domain\Model\Dto\NewsDemand;
use GeorgRinger\News\Domain\Repository\NewsRepository;
use GeorgRinger\News\Pagination\QueryResultPaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class QueryResultPaginatorTest extends FunctionalTestCase
{
    protected NewsRepository $newsRepository;

    protected array $testExtensionsToLoad = ['typo3conf/ext/news'];

    protected function setUp(): void
    {
        parent::setUp();

        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.typoscript', $frontendTypoScript);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/news_pagination.csv');
        $this->newsRepository = $this->getContainer()->get(NewsRepository::class);
    }

    /**
     * A short integration test to check that the fixtures are as expected
     */
    #[Test]
    #[IgnoreDeprecations]
    public function integration(): void
    {
        $queryResult = $this->newsRepository->findAll();
        self::assertCount(20, $queryResult);
    }

    #[Test]
    public function checkPaginatorWithDefaultConfiguration(): void
    {
        $paginator = new QueryResultPaginator($this->newsRepository->findAll());

        self::assertSame(2, $paginator->getNumberOfPages());
        self::assertSame(0, $paginator->getKeyOfFirstPaginatedItem());
        self::assertSame(9, $paginator->getKeyOfLastPaginatedItem());
        self::assertCount(10, $paginator->getPaginatedItems());
    }

    #[Test]
    public function paginatorRespectsItemsPerPageConfiguration(): void
    {
        $paginator = new QueryResultPaginator(
            $this->newsRepository->findAll(),
            1,
            3
        );

        self::assertSame(7, $paginator->getNumberOfPages());
        self::assertSame(0, $paginator->getKeyOfFirstPaginatedItem());
        self::assertSame(2, $paginator->getKeyOfLastPaginatedItem());
        self::assertCount(3, $paginator->getPaginatedItems());
    }

    #[Test]
    public function paginatorRespectsItemsPerPageConfigurationAndCurrentPage(): void
    {
        $paginator = new QueryResultPaginator(
            $this->newsRepository->findAll(),
            3,
            3
        );

        self::assertSame(7, $paginator->getNumberOfPages());
        self::assertSame(6, $paginator->getKeyOfFirstPaginatedItem());
        self::assertSame(8, $paginator->getKeyOfLastPaginatedItem());
        self::assertCount(3, $paginator->getPaginatedItems());
    }

    #[Test]
    public function paginatorProperlyCalculatesLastPage(): void
    {
        $paginator = new QueryResultPaginator(
            $this->newsRepository->findAll(),
            7,
            3
        );

        self::assertSame(7, $paginator->getNumberOfPages());
        self::assertSame(18, $paginator->getKeyOfFirstPaginatedItem());
        self::assertSame(19, $paginator->getKeyOfLastPaginatedItem());
        self::assertCount(2, $paginator->getPaginatedItems());
    }

    #[Test]
    public function withCurrentPageNumberThrowsInvalidArgumentExceptionIfCurrentPageIsLowerThanOne(): void
    {
        $this->expectExceptionCode(1573047338);

        $paginator = new QueryResultPaginator(
            $this->newsRepository->findAll(),
            1,
            3
        );
        $paginator->withCurrentPageNumber(0);
    }

    #[Test]
    public function paginatorSetsCurrentPageToLastPageIfCurrentPageExceedsMaximum(): void
    {
        $paginator = new QueryResultPaginator(
            $this->newsRepository->findAll(),
            3,
            10
        );

        self::assertEquals(2, $paginator->getCurrentPageNumber());
        self::assertEquals(2, $paginator->getNumberOfPages());
        self::assertCount(10, $paginator->getPaginatedItems()); // true?
    }

    #[Test]
    public function paginatorProperlyCalculatesOnlyOnePage(): void
    {
        $paginator = new QueryResultPaginator(
            $this->newsRepository->findAll(),
            1,
            50
        );

        self::assertSame(1, $paginator->getNumberOfPages());
        self::assertSame(0, $paginator->getKeyOfFirstPaginatedItem());
        self::assertSame(19, $paginator->getKeyOfLastPaginatedItem());
        self::assertCount(20, $paginator->getPaginatedItems());
    }

    #[DataProvider('paginatorProperlyCalculatesWithLimitAndOffsetDataProvider')]
    #[Test]
    public function paginatorProperlyCalculatesWithLimitAndOffset(array $given, array $expected): void
    {
        $demand = new NewsDemand();
        $demand->setOrder('uid asc');
        $demand->setOrderByAllowed('uid');
        $demand->setLimit($given['limit']);
        $demand->setOffset($given['offset']);
        $paginator = new QueryResultPaginator(
            $this->newsRepository->findDemanded($demand),
            $given['currentPage'],
            3,
            $given['limit'],
            $given['offset']
        );

        self::assertSame($expected['firstItemTitle'], $paginator->getPaginatedItems()[0]->getTitle());
        self::assertCount($expected['itemCount'], $paginator->getPaginatedItems());
        self::assertSame($expected['numberOfPages'], $paginator->getNumberOfPages());
    }

    public static function paginatorProperlyCalculatesWithLimitAndOffsetDataProvider(): array
    {
        return [
            'with offset' => [
                ['currentPage' => 3, 'limit' => 0, 'offset' => 3],
                ['itemCount' => 3, 'firstItemTitle' => 'News10', 'numberOfPages' => 6],
            ],
            'with limit' => [
                ['currentPage' => 2, 'limit' => 4, 'offset' => 0],
                ['itemCount' => 1, 'firstItemTitle' => 'News4', 'numberOfPages' => 2],
            ],
            'with limit and offset' => [
                ['currentPage' => 2, 'limit' => 12, 'offset' => 5],
                ['itemCount' => 3, 'firstItemTitle' => 'News9', 'numberOfPages' => 4],
            ],
        ];
    }
}
