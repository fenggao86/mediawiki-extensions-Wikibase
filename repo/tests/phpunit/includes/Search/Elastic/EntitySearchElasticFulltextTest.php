<?php

namespace Wikibase\Repo\Search\Elastic\Tests;

use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Query\BoostTemplatesFeature;
use CirrusSearch\Query\FullTextQueryBuilder;
use CirrusSearch\Query\FullTextQueryStringQueryBuilder;
use CirrusSearch\Query\InSourceFeature;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use Language;
use MediaWikiTestCase;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Repo\Search\Elastic\EntityFullTextQueryBuilder;
use Wikibase\Repo\Search\Elastic\EntitySearchElastic;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers  \Wikibase\Repo\Search\Elastic\EntitySearchElastic
 * @group Wikibase
 * @license GPL-2.0+
 * @author  Stas Malyshev
 */
class EntitySearchElasticFulltextTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		if ( !class_exists( 'CirrusSearch' ) ) {
			$this->markTestSkipped( 'CirrusSearch not installed, skipping' );
		}
	}

	/**
	 * @param Language $userLang
	 * @return EntitySearchElastic
	 */
	private function newEntitySearch( Language $userLang ) {
		$repo = WikibaseRepo::getDefaultInstance();

		return new EntitySearchElastic(
			$repo->getLanguageFallbackChainFactory(),
			new BasicEntityIdParser(),
			$userLang,
			$repo->getContentModelMappings(),
			$repo->getSettings()->getSetting( 'entitySearch' )
		);
	}

	/**
	 * @return \FauxRequest
	 */
	private function getMockRequest() {
		return new \FauxRequest( [ 'cirrusDumpQuery' => 'yes' ] );
	}

	public function searchDataProvider() {
		$tests = [];
		foreach ( glob( __DIR__ . '/../../../data/entityFulltext/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, - 6 );
			$query = json_decode( file_get_contents( $queryFile ), true );
			$expectedFile = substr( $queryFile, 0, - 5 ) . 'expected';
			$tests[$testName] = [ $query, $expectedFile ];
		}

		return $tests;
	}

	/**
	 * @return EntityNamespaceLookup
	 */
	private function getMockEntityNamespaceLookup() {
		$mockLookup = $this->getMockBuilder( EntityNamespaceLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$mockLookup->method( 'isEntityNamespace' )->willReturnCallback( function ( $ns ) {
			return $ns < 10;
		} );
		return $mockLookup;
	}

	/**
	 * @dataProvider searchDataProvider
	 * @param string[] $params
	 * @param string $expected
	 */
	public function testSearchElastic( $params, $expected ) {
		$wgSettings['statementBoost'] = [ 'P31=Q4167410' => '-10' ];
		$wgSettings['useStemming'] = [ 'en' => [ 'query' => true ] ];

		$this->setMwGlobals( [
			'wgCirrusSearchQueryStringMaxDeterminizedStates' => 500,
			'wgCirrusSearchElasticQuirks' => [],
		] );

		$config = new SearchConfig();
		$settings = [
			'any'               => 0.04,
			'lang-exact'        => 0.78,
			'lang-folded'       => 0.01,
			'lang-partial'      => 0.07,
			'fallback-exact'    => 0.38,
			'fallback-folded'   => 0.005,
			'fallback-partial'  => 0.03,
			'fallback-discount' => 0.1,
		];
		$features = [
			new InSourceFeature( $config ),
			new BoostTemplatesFeature(),
		];
		$builderSettings = $config->getProfileService()
			->loadProfileByName( SearchProfileService::FT_QUERY_BUILDER, 'default' );
		$delegate = new FullTextQueryStringQueryBuilder( $config, $features, $builderSettings['settings'] );

		$builder = new EntityFullTextQueryBuilder(
			$delegate,
			$wgSettings,
			$settings,
			$this->getMockEntityNamespaceLookup(),
			new LanguageFallbackChainFactory(),
			new ItemIdParser(),
			$params['userLang']
		);

		$context = new SearchContext( $config, $params['ns'] );
		$builder->build( $context, $params['search'], false );
		$query = $context->getQuery();
		$context->getRescore();
		$encoded = json_encode( $query->toArray(), JSON_PRETTY_PRINT );
		$this->assertFileContains( $expected, $encoded );
	}

	/**
	 * Check that other namespace searches go to delegate.
	 */
	public function testSearchDelegate() {
		$delegate = $this->getMock( FullTextQueryBuilder::class );
		$delegate->expects( $this->once() )->method( 'build' )->willReturn( false );
		$builder = new EntityFullTextQueryBuilder(
			$delegate,
			[],
			[],
			$this->getMockEntityNamespaceLookup(),
			new LanguageFallbackChainFactory(),
			new ItemIdParser(),
			'en'
		);

		$context = new SearchContext( new SearchConfig(), [ 150 ] );
		$builder->build( $context, "test", false );
	}

}
