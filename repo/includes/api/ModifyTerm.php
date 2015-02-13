<?php

namespace Wikibase\Api;

use ApiBase;
use InvalidArgumentException;
use Status;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;

/**
 * API module to set the terms for a Wikibase entity.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 */
abstract class ModifyTerm extends ModifyEntity {

	/**
	 * Creates a Summary object based on the given API call parameters.
	 * The Summary will be initializes with the appropriate action name
	 * and target language. It will not have any summary arguments set.
	 *
	 * @since 0.4
	 *
	 * @param array $params
	 *
	 * @return Summary
	 */
	protected function createSummary( array $params ) {
		$set = isset( $params['value'] ) && 0 < strlen( $params['value'] );

		$summary = parent::createSummary( $params );
		$summary->setAction( ( $set ? 'set' : 'remove' ) );
		$summary->setLanguage( $params['language'] );

		return $summary;
	}

	/**
	 * @see ModifyEntity::getRequiredPermissions()
	 *
	 * @param Entity $entity
	 * @param array $params
	 *
	 * @throws \InvalidArgumentException
	 * @return array|Status
	 */
	protected function getRequiredPermissions( Entity $entity, array $params ) {
		$permissions = parent::getRequiredPermissions( $entity, $params );
		if( $entity instanceof Item ) {
			$type = 'item';
		} else if ( $entity instanceof Property ) {
			$type = 'property';
		} else {
			throw new InvalidArgumentException( 'Unexpected Entity type when checking special page term change permissions' );
		}
		$permissions[] = $type . '-term';
		return $permissions;
	}

	/**
	 * @see ModifyEntity::getAllowedParams
	 */
	protected function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			array(
				'language' => array(
					ApiBase::PARAM_TYPE => WikibaseRepo::getDefaultInstance()->getTermsLanguages()->getLanguages(),
					ApiBase::PARAM_REQUIRED => true,
				),
				'value' => array(
					ApiBase::PARAM_TYPE => 'string',
				),
			)
		);
	}

}
