<?php

class Creativestyle_Richsnippets_Block_Jsonld extends Mage_Core_Block_Template {

	private function getProducts() {
		$product = Mage::registry( 'current_product' );
		if ( $product && $product->getEntityId() ) {
			return array( $product );
		} elseif ( Mage::getStoreConfig( 'richsnippets/general/categories' ) && Mage::registry( "current_category" ) ) {
			return $this->getLoadedProductCollection();
		}

		return array();
	}

	private function getAttributeValue( $attr, $product ) {
		$value = null;
		if ( $product ) {
			$type = $product->getResource()->getAttribute( $attr )->getFrontendInput();

			if ( $type == 'text' || $type == 'textarea' ) {
				$value = $product->getData( $attr );
			} elseif ( $type == 'select' ) {
				$value = $product->getAttributeText( $attr ) ? $product->getAttributeText( $attr ) : '';
			}
		}

		return $value;
	}

	public function getStructuredData() {

		if ( ! Mage::getStoreConfig( 'richsnippets/general/enabled' ) ) {
			return;
		}

		$debug = 0;
		if ( Mage::getStoreConfig( 'richsnippets/debug/enabled' ) ) {
			$debug = JSON_PRETTY_PRINT;
		}

		$products = $this->getProducts();
		$ret      = "";

		foreach ( $products as $product ) {
			$categoryName = Mage::registry( 'current_category' ) ? Mage::registry( 'current_category' )->getName() : '';
			$productId    = $product->getEntityId();
			$storeId      = Mage::app()->getStore()->getId();
			$currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

			$json = array(
				'availability' => $product->isAvailable() ? 'http://schema.org/InStock' : 'http://schema.org/OutOfStock',
				'category'     => $categoryName
			);

			// check if reviews are enabled in extension's backend configuration
			$review    = Mage::getStoreConfig( 'richsnippets/general/review' );
			$ts_review = Mage::getStoreConfig( 'richsnippets/trustedshops/enabled' );
			if ( $review ) {
				$reviewSummary = Mage::getModel( 'review/review/summary' );
				$ratingData    = Mage::getModel( 'review/review_summary' )->setStoreId( $storeId )->load( $productId );

				// get reviews collection
				$reviews = Mage::getModel( 'review/review' )
				               ->getCollection()
				               ->addStoreFilter( $storeId )
				               ->addStatusFilter( 1 )
				               ->addFieldToFilter( 'entity_id', 1 )
				               ->addFieldToFilter( 'entity_pk_value', $productId )
				               ->setDateOrder()
				               ->addRateVotes()
				               ->getItems();

				$reviewData = array();
				$ratings    = array();
				if ( count( $reviews ) > 0 ) {
					foreach ( $reviews as $r ) {
						foreach ( $r->getRatingVotes() as $vote ) {
							$ratings[] = $vote->getPercent();
						}

						if ( ! empty( $ratings ) ) {
							$avg = array_sum( $ratings ) / count( $ratings );
							$avg = number_format( floor( ( $avg / 20 ) * 2 ) / 2, 1 ); // average rating (1-5 range)

							$datePublished = explode( ' ', $r->getCreatedAt() );

							// another "mini-array" with schema data
							$reviewData[] = array(
								'@type'         => 'Review',
								'author'        => $this->htmlEscape( $r->getNickname() ),
								'datePublished' => str_replace( '/', '-', $datePublished[0] ),
								'name'          => $this->htmlEscape( $r->getTitle() ),
								'reviewBody'    => nl2br( $this->escapeHtml( $r->getDetail() ) ),
								'reviewRating'  => array(
									'@type'       => 'Rating',
									'ratingValue' => $avg,
								),
							);
						}
					}
				}

				// let's put review data into $json array
				$json['reviewCount'] = $reviewSummary->getTotalReviews( $product->getId() );
				$json['ratingValue'] = number_format( floor( ( $ratingData['rating_summary'] / 20 ) * 2 ) / 2, 1 ); // average rating (1-5 range)
				$json['review']      = $reviewData;
			}

			//use Desc if Shortdesc not work
			if ( $product->getShortDescription() ) {
				$descsnippet = $this->cleanAttribute( $product->getShortDescription() );
			} else {
				$descsnippet = substr( $this->cleanAttribute( $product->getDescription() ), 0, 200 );
			}

			// Set product condition
			$condition  = Mage::getStoreConfig( 'richsnippets/general/condition' );
			$store_name = Mage::getStoreConfig( 'general/store_information/name' );

			// Final array with all basic product data
			$data = array(
				'@context'    => 'http://schema.org',
				'@type'       => 'Product',
				'name'        => $product->getName(),
				'sku'         => $product->getSku(),
				'mpn'         => $product->getSku(),
				'image'       => $product->getImageUrl(),
				'url'         => $product->getProductUrl(),
				'description' => $descsnippet,
				'offers'      => array(
					'@type'         => 'Offer',
					'availability'  => $json['availability'],
					'price'         => number_format( (float) $product->getFinalPrice(), 2, '.', '' ),
					'priceCurrency' => $currencyCode,
					'category'      => $json['category'],
					'seller'        => array(
						'@type' => 'Thing',
						'name'  => $store_name,
					),
					'itemCondition' => $condition,
				),
				'brand'       => array(
					'@type' => 'Thing',
					'name'  => $store_name,
				),
			);

			// if reviews enabled - join it to $data array
			if ( $review || $ts_review ) {
				// Get Trusted Shops rating if enabled
				if ( $ts_review ) {
					$ts = $this->getTrustedshopsRating();
					if ( $ts ) {
						$json['ratingValue'] = $ts[0];
						$json['reviewCount'] = $ts[1];
					}
				}

				$data['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'bestRating'  => '5.00',
					'ratingValue' => $json['ratingValue'],
					'reviewCount' => $json['reviewCount']
				);

				if ( ! empty( $reviewData ) ) {
					$data['review'] = $reviewData;
				}
			}

			// getting all attributes from "Attributes" section of or extension's config area...
			$attributes = Mage::getStoreConfig( 'richsnippets/attributes' );

			// ... and putting them into $data array if they're not empty
			foreach ( $attributes AS $key => $value ) {
				if ( $value ) {
					$data[ $key ] = $this->getAttributeValue( $value, $product );
				}
			}

			// return $data table in JSON format
			$ret .= '<script type="application/ld+json">' . "\n" . json_encode( $data, $debug ) . "\n</script>\n";

		}

		$store_name = Mage::getStoreConfig( 'general/store_information/name' );
		$url        = Mage::getStoreConfig( 'web/unsecure/base_url' );
		$url_store  = Mage::getBaseUrl();

		// Add sitelinks search box
		if ( Mage::getStoreConfig( 'richsnippets/general/searchbox' ) ) {
			$search_url = Mage::helper( 'core' )->isModuleEnabled( 'AW_Advancedsearch' ) ? $url_store . "advancedsearch/result/?q={q}" : $url_store . "catalogsearch/result/?q={q}";
			$search     = array(
				"@context"        => "http://schema.org",
				"@type"           => "WebSite",
				"name"            => $store_name,
				"url"             => $url,
				"potentialAction" => array(
					"@type"       => "SearchAction",
					"target"      => $search_url,
					"query-input" => "required name=q",
				),
			);
			$ret .= '<script type="application/ld+json">' . "\n" . json_encode( $search, $debug ) . "\n</script>\n";
		}

		// Social links and logo
		if ( Mage::getStoreConfig( 'richsnippets/general/organization' ) ) {
			$social = explode( "\n", Mage::getStoreConfig( 'richsnippets/social/links' ) );
			$org    = array(
				"@context"     => "http://schema.org",
				"@type"        => "Organization",
				"name"         => $store_name,
				"email"        => Mage::getStoreConfig( 'trans_email/ident_general/email' ),
				"url"          => $url,
				"logo"         => Mage::getDesign()->getSkinUrl( Mage::getStoreConfig( 'design/header/logo_src' ) ),
				"contactPoint" => array(
					"@type"       => "ContactPoint",
					"telephone"   => Mage::getStoreConfig( 'general/store_information/phone' ),
					"email"       => Mage::getStoreConfig( 'trans_email/ident_support/email' ),
					"contactType" => "customer service",
				),
				"sameAs"       => array( $social ),
			);
			$ret .= '<script type="application/ld+json">' . "\n" . json_encode( $org, $debug ) . "\n</script>\n";
		}

		return $ret;
	}

	private function getLoadedProductCollection() {

		if ( Mage::app()->getRequest()->getParam( 'limit' ) ) {
			$limit = (int) Mage::app()->getRequest()->getParam( 'limit' );
		} elseif ( strpos( "grid", Mage::getStoreConfig( 'catalog/frontend/list_mode' ) ) === 0 ) {
			$limit = (int) Mage::getStoreConfig( 'catalog/frontend/grid_per_page' );
		} else {
			$limit = (int) Mage::getStoreConfig( 'catalog/frontend/list_per_page' );
		}

		$page = 1;
		if ( Mage::app()->getRequest()->getParam( 'p' ) ) {
			$page = (int) Mage::app()->getRequest()->getParam( 'p' );
		}

		/*
		$collection = Mage::registry("current_category")->getProductCollection()
			              ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
						  ->setStore(Mage::app()->getStore())
						  ->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
		                  ->setPageSize($limit)
		                  ->setCurPage($page)
						  ->addUrlRewrite()
		;
		*/

		$collection = Mage::getSingleton( 'catalog/layer' )->getProductCollection()
		                  ->addAttributeToSelect( "image" )
		                  ->setPageSize( $limit )
		                  ->setCurPage( $page );

		return $collection;
	}

	private function getTrustedshopsRating() {
		$tsId          = Mage::getStoreConfig( 'richsnippets/trustedshops/tsid' );
		$cacheFileName = Mage::getBaseDir( "tmp" ) . DS . $tsId . '.json';
		$cacheTimeOut  = 43200; // half a day
		$apiUrl        = 'http://api.trustedshops.com/rest/public/v2/shops/' . $tsId . '/quality/reviews.json';

		if ( ! function_exists( 'trustedshopscachecheck' ) ) {
			function trustedshopscachecheck( $filename_cache, $timeout = 10800 ) {
				if ( file_exists( $filename_cache ) && time() - filemtime( $filename_cache ) < $timeout ) {
					return true;
				}

				return false;
			}
		}

		// check if cached version exists
		if ( ! trustedshopscachecheck( $cacheFileName, $cacheTimeOut ) ) {
			// load fresh from API
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_HEADER, false );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_POST, false );
			curl_setopt( $ch, CURLOPT_URL, $apiUrl );
			$output = curl_exec( $ch );
			curl_close( $ch );
			// Write the contents back to the file
			// Make sure you can write to file's destination
			file_put_contents( $cacheFileName, $output );
		}

		if ( $jsonObject = json_decode( file_get_contents( $cacheFileName ), true ) ) {
			$result = $jsonObject ['response'] ['data'] ['shop'] ['qualityIndicators'] ['reviewIndicator'] ['overallMark'];
			$count  = $jsonObject ['response'] ['data'] ['shop'] ['qualityIndicators'] ['reviewIndicator'] ['activeReviewCount'];
			if ( $count > 0 ) {
				return array( $result, $count );
			}
		}

		return null;
	}

	private function cleanAttribute( $s ) {
		$s = str_replace( array( ">", "\n" ), array( "> ", " " ), $s );

		return html_entity_decode( strip_tags( $s ) );
	}

}
