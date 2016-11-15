<?php
/**
 * VRPConnector Core Plugin Class
 *
 * @package VRPConnector
 */

namespace Gueststream;

/**
 * VRPConnector Class
 */
class VRPConnector {

	/**
	 * VRP API key.
	 *
	 * @var string
	 */
	public $api_key;
	/**
	 * VRP API Url
	 *
	 * @var string
	 */
	private $api_url = 'https://www.gueststream.net/api/v1/';
	/**
	 * VRPConnector Theme Folder
	 *
	 * @var string
	 */
	public $theme = '';
	/**
	 * VRPConnector Theme Name.
	 *
	 * @var string
	 */
	public $themename = '';
	/**
	 * Default Theme Name.
	 *
	 * @var string
	 */
	public $default_theme_name = 'mountainsunset';
	/**
	 * Available Built-in themes.
	 *
	 * @var array
	 */
	public $available_themes = [ 'mountainsunset' => 'Mountain Sunset' ];
	/**
	 * Other Actions
	 *
	 * @var array
	 */
	public $otheractions = [];
	/**
	 * Time (in seconds) for API calls
	 *
	 * @var string
	 */
	public $time;
	/**
	 * Container for Debug Data.
	 *
	 * @var array
	 */
	public $debug = [];
	/**
	 * VRP Action
	 *
	 * @var bool
	 */
	public $action = false;
	/**
	 * Favorite Units
	 *
	 * @var array
	 */
	public $favorites;
	/**
	 * Search Data
	 *
	 * @var object
	 */
	public $search;
	/**
	 * Page Title
	 *
	 * @var string
	 */
	private $pagetitle;
	/**
	 * Page Description
	 *
	 * @var string
	 */
	private $pagedescription;

	/**
	 * Class Construct
	 */
	public function __construct() {
		$this->api_key = get_option( 'vrpAPI' );

		if ( $this->api_key == '' ) {
			add_action( 'admin_notices', [ $this, 'notice' ] );
		}

		$this->prepareData();
		$this->setTheme();
		$this->actions();
		$this->themeActions();
	}

	/**
	 * Use the demo API key.
	 */
	function __load_demo_key() {
		$this->api_key = '1533020d1121b9fea8c965cd2c978296';
	}

	/**
	 * init WordPress Actions, Filters & shortcodes
	 */
	public function actions() {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'setupPage' ] );
			add_action( 'admin_init', [ $this, 'registerSettings' ] );
			add_filter( 'plugin_action_links', [ $this, 'add_action_links' ], 10, 2 );
		}

		// Actions
		add_action( 'init', [ $this, 'ajax' ] );
		add_action( 'init', [ $this, 'sitemap' ] );
		add_action( 'init', [ $this, 'featuredunit' ] );
		add_action( 'init', [ $this, 'otheractions' ] );
		add_action( 'init', [ $this, 'rewrite' ] );
		add_action( 'init', [ $this, 'villafilter' ] );
		add_action( 'parse_request', [ $this, 'router' ] );
		add_action( 'update_option_vrpApiKey', [ $this, 'flush_rewrites' ], 10, 2 );
		add_action( 'update_option_vrpAPI', [ $this, 'flush_rewrites' ], 10, 2 );
		add_action( 'wp', [ $this, 'remove_filters' ] );
		add_action( 'pre_get_posts', [ $this, 'query_template' ] );

		// Filters
		add_filter( 'robots_txt', [ $this, 'robots_mod' ], 10, 2 );
		remove_filter( 'template_redirect', 'redirect_canonical' );

		// Shortcodes
		add_shortcode( 'vrpUnit', [ $this, 'vrpUnit' ] );
		add_shortcode( 'vrpUnits', [ $this, 'vrpUnits' ] );
		add_shortcode( 'vrpSearch', [ $this, 'vrpSearch' ] );
		add_shortcode( 'vrpSearchForm', [ $this, 'vrpSearchForm' ] );
		add_shortcode( 'vrpAdvancedSearchForm', [ $this, 'vrpAdvancedSearchForm' ] );
		add_shortcode( 'vrpComplexes', [ $this, 'vrpComplexes' ] );
		add_shortcode( 'vrpComplexSearch', [ $this, 'vrpComplexSearch' ] );
		// add_shortcode("vrpAreaList", array($this, "vrpAreaList"));
		// add_shortcode("vrpSpecials", array($this, "vrpSpecials"));
		// add_shortcode("vrpLinks", array($this, "vrpLinks"));
		add_shortcode( 'vrpshort', [ $this, 'vrpShort' ] );
		add_shortcode( 'vrpFeaturedUnit', [ $this, 'vrpFeaturedUnit' ] );
		add_shortcode( 'vrpCheckUnitAvailabilityForm', [ $this, 'vrpCheckUnitAvailabilityForm' ] );

		// Widgets
		add_filter( 'widget_text', 'do_shortcode' );
		add_action( 'widgets_init', function () {
			register_widget( 'Gueststream\Widgets\vrpSearchFormWidget' );
		} );
	}

	/**
	 * Set the plugin theme used & include the theme functions file.
	 */
	public function setTheme() {
		$plugin_theme_Folder = VRP_PATH . 'themes/';
		$theme               = get_option( 'vrpTheme' );

		if ( ! $theme ) {
			$theme           = $this->default_theme_name;
			$this->themename = $this->default_theme_name;
			$this->theme     = $plugin_theme_Folder . $this->default_theme_name;
		} else {
			$this->theme     = $plugin_theme_Folder . $theme;
			$this->themename = $theme;
		}
		$this->themename = $theme;

		if ( file_exists( get_stylesheet_directory() . '/vrp/functions.php' ) ) {
			include get_stylesheet_directory() . '/vrp/functions.php';
		} else {
			include $this->theme . '/functions.php';
		}
	}

	/**
	 * Alters WP_Query to tell it to load the page template instead of home.
	 *
	 * @param WP_Query $query
	 *
	 * @return WP_Query
	 */
	public function query_template( $query ) {
		if ( ! isset( $query->query_vars['action'] ) ) {
			return $query;
		}
		$query->is_page = true;
		$query->is_home = false;

		return $query;
	}

	public function themeActions() {
		$theme = new $this->themename;
		if ( method_exists( $theme, 'actions' ) ) {
			$theme->actions();
		}
	}

	public function otheractions() {
		if ( isset( $_GET['otherslug'] ) && $_GET['otherslug'] != '' ) {
			$theme = $this->themename;
			$theme = new $theme;
			$func  = $theme->otheractions;
			$func2 = $func[ $_GET['otherslug'] ];
			call_user_method( $func2, $theme );
		}
	}

	/**
	 * Uses built-in rewrite rules to get pretty URL. (/vrp/)
	 */
	public function rewrite() {
		add_rewrite_tag( '%action%', '([^&]+)' );
		add_rewrite_tag( '%slug%', '([^&]+)' );
		add_rewrite_rule( '^vrp/([^/]*)/([^/]*)/?', 'index.php?action=$matches[1]&slug=$matches[2]', 'top' );
	}

	/**
	 * Only on activation.
	 */
	static function rewrite_activate() {
		add_rewrite_tag( '%action%', '([^&]+)' );
		add_rewrite_tag( '%slug%', '([^&]+)' );
		add_rewrite_rule( '^vrp/([^/]*)/([^/]*)/?', 'index.php?action=$matches[1]&slug=$matches[2]', 'top' );

	}

	/**
	 * @param $old
	 * @param $new
	 */
	function flush_rewrites( $old, $new ) {
		flush_rewrite_rules();
	}

	/**
	 * Sets up action and slug as query variable.
	 *
	 * @param $vars [] $vars Query String Variables.
	 *
	 * @return $vars[]
	 */
	public function query_vars( $vars ) {
		array_push( $vars, 'action', 'slug', 'other' );

		return $vars;
	}

	/**
	 * Checks to see if VRP slug is active, if so, sets up a page.
	 *
	 * @return bool
	 */
	public function router( $query ) {
		if ( ! isset( $query->query_vars['action'] ) ) {
			return false;
		}

		if ( $query->query_vars['action'] == 'xml' ) {
			$this->xmlexport();
		}

		if ( $query->query_vars['action'] == 'flipkey' ) {
			$this->getflipkey();
		}

		if ( $query->query_vars['action'] == 'ical' ) {
			if ( ! isset( $query->query_vars['slug'] ) ) {
				return false;
			}
			$this->displayIcal( $query->query_vars['slug'] );
		}

		add_filter( 'the_posts', [ $this, 'filterPosts' ], 1, 2 );
	}

	/**
	 * @param $posts
	 *
	 * @return array
	 */
	public function filterPosts( $posts, $query ) {
		if ( ! isset( $query->query_vars['action'] ) || ! isset( $query->query_vars['slug'] ) ) {
			return $posts;
		}

		$content         = '';
		$pagetitle       = '';
		$pagedescription = '';
		$action          = $query->query_vars['action'];
		$slug            = $query->query_vars['slug'];

		switch ( $action ) {
			case 'unit':
				$data2 = $this->call( 'getunit/' . $slug );
				$data  = json_decode( $data2 );

				if ( isset( $data->SEOTitle ) ) {
					$pagetitle = $data->SEOTitle;
				} else {
					$pagetitle = $data->Name;
				}

				$pagedescription = $data->SEODescription;

				if ( ! isset( $data->id ) ) {
					global $wp_query;
					$wp_query->is_404 = true;
				}

				if ( isset( $data->Error ) ) {
					$content = $this->loadTheme( 'error', $data );
					break;
				}

				$content = $this->loadTheme( 'unit', $data );
				break;

			case 'complex': // If Complex Page.
				$data = json_decode( $this->call( 'getcomplex/' . $slug ) );

				$pagetitle = $data->name;

				if ( isset( $data->Error ) ) {
					$content = $this->loadTheme( 'error', $data );
				} else {
					$content = $this->loadTheme( 'complex', $data );
				}
				$pagetitle = $data->name;

				break;

			case 'favorites':
				$content = 'hi';
				switch ( $slug ) {
					case 'add':
						$this->addFavorite();
						break;
					case 'remove':
						$this->removeFavorite();
						break;
					case 'json':
						echo json_encode( $this->favorites );
						exit;
						break;
					default:
						$content   = $this->showFavorites();
						$pagetitle = 'Favorites';
						break;
				}
				break;

			case 'specials': // If Special Page.
				$content   = $this->specialPage( $slug );
				$pagetitle = $this->pagetitle;
				break;

			case 'search': // If Search Page.
				$data = json_decode( $this->search() );

				if ( ! empty( $data->count ) ) {
					$data = $this->prepareSearchResults( $data );
				}

				if ( isset( $_GET['json'] ) ) {
					echo json_encode( $data, JSON_PRETTY_PRINT );
					exit;
				}

				if ( isset( $data->type ) ) {
					$content = $this->loadTheme( $data->type, $data );
				} else {
					$content = $this->loadTheme( 'results', $data );
				}

				$pagetitle = 'Search Results';
				break;

			case 'complexsearch': // If Search Page.
				$data = json_decode( $this->complexsearch() );
				if ( isset( $data->type ) ) {
					$content = $this->loadTheme( $data->type, $data );
				} else {
					$content = $this->loadTheme( 'complexresults', $data );
				}
				$pagetitle = 'Search Results';
				break;

			case 'book':
				if ( $slug == 'dobooking' ) {
					if ( isset( $_SESSION['package'] ) ) {
						$_POST['booking']['packages'] = $_SESSION['package'];
					}
				}

				if ( isset( $_POST['email'] ) ) {
					$userinfo             = $this->doLogin( $_POST['email'], $_POST['password'] );
					$_SESSION['userinfo'] = $userinfo;
					if ( ! isset( $userinfo->Error ) ) {
						$query->query_vars['slug'] = 'step3';
					}
				}

				if ( isset( $_POST['booking'] ) ) {
					$_SESSION['userinfo'] = $_POST['booking'];
				}

				$data = json_decode( $_SESSION['bookingresults'] );
				if ( $data->ID != $_GET['obj']['PropID'] ) {
					$data      = json_decode( $this->checkavailability( false, true ) );
					$data->new = true;
				}

				if ( $slug != 'confirm' ) {
					$data      = json_decode( $this->checkavailability( false, true ) );
					$data->new = true;
				}

				$data->PropID       = $_GET['obj']['PropID'];
				$data->booksettings = $this->bookSettings( $data->PropID );

				if ( $slug == 'step1' ) {
					unset( $_SESSION['package'] );
				}

				$data->package              = new \stdClass;
				$data->package->packagecost = '0.00';
				$data->package->items       = [];

				if ( isset( $_SESSION['package'] ) ) {
					$data->package = $_SESSION['package'];
				}

				if ( $slug == 'step1a' ) {
					if ( isset( $data->booksettings->HasPackages ) ) {
						$a              = date( 'Y-m-d', strtotime( $data->Arrival ) );
						$d              = date( 'Y-m-d', strtotime( $data->Departure ) );
						$data->packages = json_decode( $this->call( "getpackages/$a/$d/" ) );
					} else {
						$query->query_vars['slug'] = 'step2';
					}
				}

				if ( $slug == 'step3' ) {
					$data->form = json_decode( $this->call( 'bookingform/' ) );
				}

				if ( $slug == 'confirm' ) {
					$data->thebooking = json_decode( $_SESSION['bresults'] );
					$pagetitle        = 'Reservations';
					$content          = $this->loadTheme( 'confirm', $data );
				} else {
					$pagetitle = 'Reservations';
					$content   = $this->loadTheme( 'booking', $data );
				}
				break;

			case 'xml':
				$content   = '';
				$pagetitle = '';
				break;
		}

		return [ new DummyResult( 0, $pagetitle, $content, $pagedescription ) ];
	}

	private function specialPage( $slug ) {
		if ( $slug == 'list' ) {
			// Special by Category
			$data            = json_decode( $this->call( 'getspecialsbycat/1' ) );
			$this->pagetitle = 'Specials';

			return $this->loadTheme( 'specials', $data );
		}

		if ( is_numeric( $slug ) ) {
			// Special by ID
			$data            = json_decode( $this->call( 'getspecialbyid/' . $slug ) );
			$this->pagetitle = $data->title;

			return $this->loadTheme( 'special', $data );
		}

		if ( is_string( $slug ) ) {
			// Special by slug
			$data            = json_decode( $this->call( 'getspecial/' . $slug ) );
			$this->pagetitle = $data->title;

			return $this->loadTheme( 'special', $data );
		}
	}

	public function villafilter() {
		if ( ! $this->is_vrp_page() ) {
			return;
		}

		if ( 'complexsearch' == $this->action ) {
			if ( $_GET['search']['type'] == 'Villa' ) {
				$this->action = 'search';
				global $wp_query;
				$wp_query->query_vars['action'] = $this->action;
			}
		}
	}

	public function searchjax() {
		if ( isset( $_GET['search']['arrival'] ) ) {
			$_SESSION['arrival'] = $_GET['search']['arrival'];
		}

		if ( isset( $_GET['search']['departure'] ) ) {
			$_SESSION['depart'] = $_GET['search']['departure'];
		}

		ob_start();
		$results = json_decode( $this->search() );

		$units = $results->results;

		include TEMPLATEPATH . '/vrp/unitsresults.php';
		$content = ob_get_contents();
		ob_end_clean();
		echo wp_kses_post( $content );
	}

	public function search() {
		$obj = new \stdClass();

		foreach ( $_GET['search'] as $k => $v ) {
			$obj->$k = $v;
		}

		if ( ! empty( $_GET['page'] ) ) {
			$obj->page = (int) $_GET['page'];
		} else {
			$obj->page = 1;
		}

		if ( empty( $obj->limit ) ) {
			$obj->limit = 10;
			if ( isset( $_GET['show'] ) ) {
				$obj->limit = (int) $_GET['show'];
			}
		}

		if ( ! empty( $obj->arrival ) ) {
			if ( $obj->arrival == 'Not Sure' ) {
				$obj->arrival = '';
				$obj->depart  = '';
				$obj->showall = 1;
			} else {
				$obj->arrival = date( 'm/d/Y', strtotime( $obj->arrival ) );
			}
		} else {
			$obj->showall = 1;
		}

		$search['search'] = json_encode( $obj );

		if ( isset( $_GET['specialsearch'] ) ) {
			// This might only be used by suite-paradise.com but is available
			// To all ISILink based PMS softwares.
			return $this->call( 'specialsearch', $search );
		}

		return $this->call( 'search', $search );
	}

	public function complexsearch() {
		$url = $this->api_url . $this->api_key . '/complexsearch3/';

		$obj = new \stdClass();
		foreach ( $_GET['search'] as $k => $v ) {
			$obj->$k = $v;
		}
		if ( isset( $_GET['page'] ) ) {
			$obj->page = (int) $_GET['page'];
		} else {
			$obj->page = 1;
		}
		if ( isset( $_GET['show'] ) ) {
			$obj->limit = (int) $_GET['show'];
		} else {
			$obj->limit = 10;
		}
		if ( $obj->arrival == 'Not Sure' ) {
			$obj->arrival = '';
			$obj->depart  = '';
		}

		$search['search'] = json_encode( $obj );
		$results          = $this->call( 'complexsearch3', $search );

		return $results;
	}

	/**
	 * Loads the VRP Theme.
	 *
	 * @param string $section
	 * @param        $data [] $data
	 *
	 * @return string
	 */
	public function loadTheme( $section, $data = [] ) {
		$wptheme = get_stylesheet_directory() . "/vrp/$section.php";

		if ( file_exists( $wptheme ) ) {
			$load = $wptheme;
		} else {
			$load = $this->theme . '/' . $section . '.php';
		}

		$this->debug['data']       = $data;
		$this->debug['theme_file'] = $load;

		ob_start();
		include $load;
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
	 * VRP Ajax request handling
	 *
	 * @return bool
	 */
	public function ajax() {
		if ( ! isset( $_GET['vrpjax'] ) || ! isset( $_GET['act'] ) ) {
			return false;
		}

		$act = $_GET['act'];

		if ( method_exists( $this, $act ) ) {

			if ( isset( $_GET['par'] ) ) {
				$this->$act( $_GET['par'] );
				die();
			}

			$this->$act();
		}

		die();
	}

	public function checkavailability( $par = false, $ret = false ) {
		set_time_limit( 30 );

		$fields_string = 'obj=' . json_encode( $_GET['obj'] );
		$results       = $this->call( 'checkavail', $fields_string );

		if ( $ret == true ) {
			$_SESSION['bookingresults'] = $results;

			return $results;
		}

		if ( $par != false ) {
			$_SESSION['bookingresults'] = $results;
			echo $results;

			return true;
		}

		$res = json_decode( $results );

		if ( isset( $res->Error ) ) {
			echo esc_html( $res->Error );
		} else {
			$_SESSION['bookingresults'] = $results;
			echo '1';
		}
	}

	public function processbooking( $par = false, $ret = false ) {
		if ( isset( $_POST['booking']['comments'] ) ) {
			$_POST['booking']['comments'] = urlencode( $_POST['booking']['comments'] );
		}

		$fields_string = 'obj=' . json_encode( $_POST['booking'] );
		$results       = $this->call( 'processbooking', $fields_string );
		$res           = json_decode( $results );
		if ( isset( $res->Results ) ) {
			$_SESSION['bresults'] = json_encode( $res->Results );
		}

		echo $results;

		return;
	}

	public function addtopackage() {
		$TotalCost = $_GET['TotalCost'];
		if ( ! isset( $_GET['package'] ) ) {
			unset( $_SESSION['package'] );
			$obj              = new \stdClass();
			$obj->packagecost = '$0.00';

			$obj->TotalCost = '$' . number_format( $TotalCost, 2 );
			echo json_encode( $obj );

			return false;
		}

		$currentpackage        = new \stdClass();
		$currentpackage->items = [];
		$grandtotal            = 0;
		// ID & QTY
		$package = $_GET['package'];
		$qty     = $_GET['qty'];
		$cost    = $_GET['cost'];
		$name    = $_GET['name'];
		foreach ( $package as $v ) :
			$amount                      = $qty[ $v ]; // Qty of item.
			$obj                         = new \stdClass();
			$obj->name                   = $name[ $v ];
			$obj->qty                    = $amount;
			$obj->total                  = $cost[ $v ] * $amount;
			$grandtotal                  = $grandtotal + $obj->total;
			$currentpackage->items[ $v ] = $obj;
		endforeach;

		$TotalCost        = $TotalCost + $grandtotal;
		$obj              = new \stdClass();
		$obj->packagecost = '$' . number_format( $grandtotal, 2 );

		$obj->TotalCost = '$' . number_format( $TotalCost, 2 );
		echo json_encode( $obj );
		$currentpackage->packagecost = $grandtotal;
		$currentpackage->TotalCost   = $TotalCost;
		$_SESSION['package']         = $currentpackage;
	}

	public function getspecial() {
		return json_decode( $this->call( 'getonespecial' ) );
	}

	public function getTheSpecial( $id ) {
		$data = json_decode( $this->call( 'getspecialbyid/' . $id ) );

		return $data;
	}

	public function sitemap() {
		if ( ! isset( $_GET['vrpsitemap'] ) ) {
			return false;
		}
		$data = json_decode( $this->call( 'allvrppages' ) );
		ob_start();
		include 'xml.php';
		$content = ob_get_contents();
		ob_end_clean();
		echo $content;
		exit;
	}

	public function xmlexport() {
		header( 'Content-type: text/xml' );
		$this->customcall( 'generatexml' );
		exit;
	}

	public function displayIcal( $unitSlug ) {
		$unitData = json_decode(
			$this->call( 'getunit/' . $unitSlug )
		);

		$vCalendar = new \Eluceo\iCal\Component\Calendar( site_url( '/vrp/ical/' . $unitSlug ) );

		foreach ( $unitData->avail as $bookedDate ) {
			$vEvent = new \Eluceo\iCal\Component\Event();
			$vEvent
				->setDtStart( new \DateTime( $bookedDate->start_date ) )
				->setDtEnd( new \DateTime( $bookedDate->end_date ) )
				->setNoTime( true )
				->setSummary( 'Booked' );
			$vCalendar->addComponent( $vEvent );
		}

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cal.ics"' );
		echo $vCalendar->render();

		exit;
	}

	public function getUnitBookedDates( $unitSlug ) {
		$unitDataJson = $this->call( 'getunit/' . (string) $unitSlug );
		$unitData     = json_decode( $unitDataJson );

		$unitBookedDates = [
			'bookedDates' => [],
			'noCheckin'   => [],
		];

		foreach ( $unitData->avail as $v ) {

			$fromDateTS = strtotime( '+1 Day', strtotime( $v->start_date ) );
			$toDateTS   = strtotime( $v->end_date );

			array_push( $unitBookedDates['noCheckin'], date( 'n-j-Y', strtotime( $v->start_date ) ) );

			for ( $currentDateTS = $fromDateTS; $currentDateTS < $toDateTS; $currentDateTS += ( 60 * 60 * 24 ) ) {
				$currentDateStr = date( 'n-j-Y', $currentDateTS );
				array_push( $unitBookedDates['bookedDates'], $currentDateStr );
			}
		}

		echo json_encode( $unitBookedDates );
	}

	//
	// Wordpress Filters
	//
	public function robots_mod( $output, $public ) {
		$siteurl = get_option( 'siteurl' );
		$output .= 'Sitemap: ' . $siteurl . "/?vrpsitemap=1 \n";

		return $output;
	}

	public function add_action_links( $links, $file ) {
		if ( $file == 'vrpconnector/VRPConnector.php' && function_exists( 'admin_url' ) ) {
			$settings_link = '<a href="' . admin_url( 'options-general.php?page=VRPConnector' ) . '">' . __( 'Settings' ) . '</a>';
			array_unshift( $links, $settings_link ); // before other links
		}

		return $links;
	}

	//
	// API Calls
	//
	/**
	 * Make a call to the VRPc API
	 *
	 * @param       $call
	 * @param array $params
	 *
	 * @return string
	 */
	public function call( $call, $params = [] ) {
		$cache_key = md5( $call . json_encode( $params ) );
		$results   = wp_cache_get( $cache_key, 'vrp' );
		if ( false == $results ) {
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $this->api_url . $this->api_key . '/' . $call );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HEADER, 0 );
			$results = curl_exec( $ch );
			curl_close( $ch );
			wp_cache_set( $cache_key, $results, 'vrp', 300 ); // 5 Minutes.
		}

		return $results;
	}

	public function customcall( $call ) {
		echo $this->call( "customcall/$call" );
	}

	public function custompost( $call ) {
		$obj = new \stdClass();
		foreach ( $_POST['obj'] as $k => $v ) {
			$obj->$k = $v;
		}

		$search['search']       = json_encode( $obj );
		$results                = $this->call( $call, $search );
		$this->debug['results'] = $results;
		echo $results;
	}

	public function bookSettings( $propID ) {
		return json_decode( $this->call( 'booksettings/' . $propID ) );
	}

	/**
	 * Get available search options.
	 *
	 * With no arguments, will show search options against all active units.
	 *
	 * With filters argument it will pull back search options based on units that meet the filtered requirements
	 * $filters = ['City' => 'Denver','View' => 'Mountains']
	 *
	 * @return mixed
	 */
	public function searchoptions( array $filters = null ) {
		if ( is_array( $filters ) ) {
			$queryString = http_build_query( [ 'filters' => $filters ] );

			return json_decode( $this->call( 'searchoptions', $queryString ) );
		}

		$searchOptions = json_decode( $this->call( 'searchoptions' ) );

		$searchOptions->minbaths = ( empty( $searchOptions->minbaths ) ) ? 1 : $searchOptions->minbaths;

		return $searchOptions;
	}

	/**
	 * List out property names. Useful in listing names for propery select box.
	 */
	function proplist() {
		$data = $this->call( 'namelist' );

		return json_decode( $data );
	}

	/**
	 * Get a featured unit
	 *
	 * @ajax
	 */
	public function featuredunit() {
		if ( isset( $_GET['featuredunit'] ) ) {
			$featured_unit = json_decode( $this->call( 'featuredunit' ) );
			wp_send_json( $featured_unit );
			exit;
		}
	}

	public function allSpecials() {
		return json_decode( $this->call( 'allspecials' ) );
	}

	/**
	 * Get flipkey reviews for a given unit.
	 *
	 * @ajax
	 */
	public function getflipkey() {
		$id   = $_GET['slug'];
		$call = "getflipkey/?unit_id=$id";
		$data = $this->customcall( $call );
		echo '<!DOCTYPE html><html>';
		echo '<body>';
		echo wp_kses_post( $data );
		echo '</body></html>';
		exit;
	}

	public function saveUnitPageView( $unit_id = false ) {
		if ( ! $unit_id ) {
			return false;
		}

		$params['params'] = json_encode( [
			'unit_id'    => $unit_id,
			'ip_address' => $_SERVER['REMOTE_ADDR'],
		] );

		$this->call( 'customAction/unitpageviews/saveUnitPageView', $params );

		return true;
	}

	//
	// VRP Favorites/Compare
	//
	private function addFavorite() {
		if ( ! isset( $_GET['unit'] ) ) {
			return false;
		}

		if ( ! isset( $_SESSION['favorites'] ) ) {
			$_SESSION['favorites'] = [];
		}

		$unit_id = $_GET['unit'];
		if ( ! in_array( $unit_id, $_SESSION['favorites'] ) ) {
			array_push( $_SESSION['favorites'], $unit_id );
			$this->setFavoriteCookie( $_SESSION['favorites'] );
		}

		exit;
	}

	private function removeFavorite() {
		if ( ! isset( $_GET['unit'] ) ) {
			return false;
		}
		if ( ! isset( $_SESSION['favorites'] ) ) {
			return false;
		}
		$unit = $_GET['unit'];
		foreach ( $this->favorites as $key => $unit_id ) {
			if ( $unit == $unit_id ) {
				unset( $this->favorites[ $key ] );
				$this->setFavoriteCookie( $this->favorites );
			}
		}
		$_SESSION['favorites'] = $this->favorites;
		exit;
	}

	private function setFavoriteCookie( $favorites ) {
		setcookie( 'vrpFavorites', serialize( $favorites ), time() + 60 * 60 * 24 * 30 );
	}

	public function savecompare() {
		$obj              = new \stdClass();
		$obj->compare     = $_SESSION['compare'];
		$obj->arrival     = $_SESSION['arrival'];
		$obj->depart      = $_SESSION['depart'];
		$search['search'] = json_encode( $obj );
		$results          = $this->call( 'savecompare', $search );

		return $results;
	}

	public function showFavorites() {
		if ( isset( $_GET['shared'] ) ) {
			$_SESSION['cp'] = 1;
			$id             = (int) $_GET['shared'];
			$source         = '';
			if ( isset( $_GET['source'] ) ) {
				$source = $_GET['source'];
			}
			$data                = json_decode( $this->call( 'getshared/' . $id . '/' . $source ) );
			$_SESSION['compare'] = $data->compare;
			$_SESSION['arrival'] = $data->arrival;
			$_SESSION['depart']  = $data->depart;
		}

		$obj = new \stdClass();

		if ( ! isset( $_GET['favorites'] ) ) {
			if ( count( $this->favorites ) == 0 ) {
				return $this->loadTheme( 'vrpFavoritesEmpty' );
			}

			$url_string = site_url() . '/vrp/favorites/show?';
			foreach ( $this->favorites as $unit_id ) {
				$url_string .= '&favorites[]=' . $unit_id;
			}
			header( 'Location: ' . $url_string );
			exit;
		}

		$compare               = $_GET['favorites'];
		$_SESSION['favorites'] = $compare;

		if ( isset( $_GET['arrival'] ) ) {
			$obj->arrival        = $_GET['arrival'];
			$obj->departure      = $_GET['depart'];
			$_SESSION['arrival'] = $obj->arrival;
			$_SESSION['depart']  = $obj->departure;
		} else {
			if ( isset( $_SESSION['arrival'] ) ) {
				$obj->arrival   = $_SESSION['arrival'];
				$obj->departure = $_SESSION['depart'];
			}
		}

		$obj->items = $compare;
		sort( $obj->items );
		$search['search'] = json_encode( $obj );
		$results          = json_decode( $this->call( 'compare', $search ) );
		if ( count( $results->results ) == 0 ) {
			return $this->loadTheme( 'vrpFavoritesEmpty' );
		}

		$results = $this->prepareSearchResults( $results );

		return $this->loadTheme( 'vrpFavorites', $results );
	}

	private function setFavorites() {
		if ( isset( $_COOKIE['vrpFavorites'] ) && ! isset( $_SESSION['favorites'] ) ) {
			$_SESSION['favorites'] = unserialize( $_COOKIE['vrpFavorites'] );
		}

		if ( isset( $_SESSION['favorites'] ) ) {
			foreach ( $_SESSION['favorites'] as $unit_id ) {
				$this->favorites[] = (int) $unit_id;
			}

			return;
		}

		$this->favorites = [];

		return;
	}

	//
	// Shortcode methods
	//
	/**
	 * [vrpComplexes] Shortcode
	 *
	 * @param array $items
	 *
	 * @return string
	 */
	public function vrpComplexes( $items = [] ) {
		$items['page'] = 1;

		if ( isset( $_GET['page'] ) ) {
			$items['page'] = (int) $_GET['page'];
		}

		if ( isset( $_GET['beds'] ) ) {
			$items['beds'] = (int) $_GET['beds'];
		}
		if ( isset( $_GET['minbed'] ) ) {
			$items['minbed'] = (int) $_GET['minbed'];
			$items['maxbed'] = (int) $_GET['maxbed'];
		}

		$obj       = new \stdClass();
		$obj->okay = 1;
		if ( count( $items ) != 0 ) {
			foreach ( $items as $k => $v ) {
				$obj->$k = $v;
			}
		}

		$search['search'] = json_encode( $obj );
		$results          = $this->call( 'allcomplexes', $search );
		$results          = json_decode( $results );
		$content          = $this->loadTheme( 'vrpComplexes', $results );

		return $content;
	}

	public function vrpUnit( $args = [] ) {

		if ( empty( $args['page_slug'] ) ) {
			return '<span style="color:red;font-size: 1.2em;">page_slug argument MUST be present when using this shortcode. example: [vrpUnit page_slug="my_awesome_unit"]</span>';
		}

		$json_unit_data = $this->call( "getunit/" . $args['page_slug'] );
		$unit_data     = json_decode( $json_unit_data );

		if ( empty( $unit_data->id ) ) {
			return '<span style="color:red;font-size: 1.2em;">' . $args['page_slug'] . ' is an invalid unit page slug.  Unit not found.</span>';
		}

		return $this->loadTheme( "unit", $unit_data );

	}


	/**
	 * [vrpUnits] Shortcode
	 *
	 * @param array $items
	 *
	 * @return string
	 */
	public function vrpUnits( $items = [] ) {
		$items['showall'] = 1;
		if ( isset( $_GET['page'] ) ) {
			$items['page'] = (int) $_GET['page'];
		}

		if ( isset( $_GET['beds'] ) ) {
			$items['beds'] = (int) $_GET['beds'];
		}

		if ( isset( $_GET['search'] ) ) {
			foreach ( $_GET['search'] as $k => $v ) :
				$items[ $k ] = $v;
			endforeach;
		}

		if ( isset( $_GET['minbed'] ) ) {
			$items['minbed'] = (int) $_GET['minbed'];
			$items['maxbed'] = (int) $_GET['maxbed'];
		}

		$obj       = new \stdClass();
		$obj->okay = 1;
		if ( count( $items ) != 0 ) {
			foreach ( $items as $k => $v ) {
				$obj->$k = $v;
			}
		}

		if ( ! isset( $obj->sort ) ) {
			$obj->sort = 'Name';
		}

		if ( ! isset( $obj->order ) ) {
			$obj->order = 'low';
		}

		$search['search'] = json_encode( $obj );
		$results          = $this->call( 'allunits', $search );
		$results          = json_decode( $results );
		$content          = $this->loadTheme( 'vrpUnits', $results );

		return $content;
	}

	/**
	 * [vrpSearchForm] Shortcode
	 *
	 * @return string
	 */
	public function vrpSearchForm() {
		$data = '';
		$page = $this->loadTheme( 'vrpSearchForm', $data );

		return $page;
	}

	/**
	 * [vrpAdvancedSearch] Shortcode
	 *
	 * @return string
	 */
	public function vrpAdvancedSearchForm() {
		$data = '';
		$page = $this->loadTheme( 'vrpAdvancedSearchForm', $data );

		return $page;
	}

	/**
	 * [vrpSearch] Shortcode
	 *
	 * @param array $arr
	 *
	 * @return string
	 */
	public function vrpSearch( $arr = [] ) {
		if ( count( $arr ) > 0 ) {
			foreach ( $arr as $key => $value ) {
				// WP makes all keys lower case.  We should try and set most keys with ucfirst()
				if ( $key == 'featured' ) {
					unset( $arr['featured'] );
					// the value of Featured -must- be 1.
					$arr['Featured'] = 1;
				}
			}
		}

		if ( empty( $_GET['search'] ) ) {
			$_GET['search'] = [];
		}

		$_GET['search']            = array_merge( $arr, $_GET['search'] );
		$_GET['search']['showall'] = 1;
		$data                      = $this->search();
		$data                      = json_decode( $data );

		if ( $data->count > 0 ) {
			$data = $this->prepareSearchResults( $data );
		}

		if ( isset( $data->type ) ) {
			$content = $this->loadTheme( $data->type, $data );
		} else {
			$content = $this->loadTheme( 'results', $data );
		}

		return $content;
	}

	/**
	 * [vrpComplexSearch]
	 *
	 * @param array $arr
	 *
	 * @return string
	 */
	public function vrpcomplexsearch( $arr = [] ) {
		foreach ( $arr as $k => $v ) :
			if ( stristr( $v, '|' ) ) {
				$arr[ $k ] = explode( '|', $v );
			}
		endforeach;
		$_GET['search']            = $arr;
		$_GET['search']['showall'] = 1;

		$this->time = microtime( true );
		$data       = $this->complexsearch();

		$this->time = round( ( microtime( true ) - $this->time ), 4 );
		$data       = json_decode( $data );
		if ( isset( $data->type ) ) {
			$content = $this->loadTheme( $data->type, $data );
		} else {
			$content = $this->loadTheme( 'complexresults', $data );
		}

		return $content;
	}

	/**
	 * [vrpAreaList] Shortcode
	 *
	 * @param $arr
	 *
	 * @return string
	 */
	public function vrpAreaList( $arr ) {
		$area    = $arr['area'];
		$r       = $this->call( "areabymainlocation/$area" );
		$data    = json_decode( $r );
		$content = $this->loadTheme( 'arealist', $data );

		return $content;
	}

	/**
	 * [vrpSpecials] Shortcode
	 *
	 * @param array $items
	 *
	 * @return string
	 *
	 * @todo support getOneSpecial
	 */
	public function vrpSpecials( $items = [] ) {
		if ( ! isset( $items['cat'] ) ) {
			$items['cat'] = 1;
		}

		if ( isset( $items['special_id'] ) ) {
			$data = json_decode( $this->call( 'getspecialbyid/' . $items['special_id'] ) );
		} else {
			$data = json_decode( $this->call( 'getspecialsbycat/' . $items['cat'] ) );
		}

		return $this->loadTheme( 'vrpSpecials', $data );
	}

	/**
	 * [vrpLinks] Shortcode
	 *
	 * @param $items
	 *
	 * @return string
	 */
	public function vrpLinks( $items ) {
		$items['showall'] = true;

		switch ( $items['type'] ) {
			case 'Condo';
				$call = '/allcomplexes/';
				break;
			case 'Villa';
				$call = '/allunits/';
				break;
		}

		$obj       = new \stdClass();
		$obj->okay = 1;
		if ( count( $items ) != 0 ) {
			foreach ( $items as $k => $v ) {
				$obj->$k = $v;
			}
		}

		$search['search'] = json_encode( $obj );
		$results          = json_decode( $this->call( $call, $search ) );

		$ret = "<ul style='list-style:none'>";
		if ( $items['type'] == 'Villa' ) {
			foreach ( $results->results as $v ) :
				$ret .= "<li><a href='/vrp/unit/$v->page_slug'>$v->Name</a></li>";
			endforeach;
		} else {
			foreach ( $results as $v ) :
				$ret .= "<li><a href='/vrp/complex/$v->page_slug'>$v->name</a></li>";
			endforeach;
		}
		$ret .= '</ul>';

		return $ret;
	}

	/**
	 * [vrpShort] Shortcode
	 *
	 * This is only here for legacy support.
	 *  Suite-Paradise.com
	 *
	 * @param $params
	 *
	 * @return string
	 */
	public function vrpShort( $params ) {
		if ( $params['type'] == 'resort' ) {
			$params['type'] = 'Location';
		}

		if (
			( isset( $params['attribute'] ) && $params['attribute'] == true ) ||
			( ( $params['type'] == 'complex' ) || $params['type'] == 'View' )
		) {
			$items['attributes'] = true;
			$items['aname']      = $params['type'];
			$items['value']      = $params['value'];
		} else {
			$items[ $params['type'] ] = $params['value'];
		}

		$items['sort']  = 'Name';
		$items['order'] = 'low';

		return $this->loadTheme( 'vrpShort', $items );
	}

	public function vrpCheckUnitAvailabilityForm( $args ) {
		if ( empty( $args['unit_slug'] ) ) {
			return '<span style="color:red;font-size: 1.2em;">unit_slug argument MUST be present when using this shortcode. example: [vrpCheckUnitAvailabilityForm unit_slug="my_awesome_unit"]</span>';
		}

		global $vrp;

		if ( empty( $vrp ) ) {
			return '<span style="color:red;font-size: 1.2em;">VRPConnector plugin must be enabled in order to use this shortcode.</span>';
		}

		$jsonUnitData = $vrp->call( 'getunit/' . $args['unit_slug'] );
		$unitData     = json_decode( $jsonUnitData );

		if ( empty( $unitData->id ) ) {
			return '<span style="color:red;font-size: 1.2em;">' . $args['unit_slug'] . ' is an invalid unit page slug.  Unit not found.</span>';
		}

		return $vrp->loadTheme( 'vrpCheckUnitAvailabilityForm', $unitData );
	}

	public function vrpFeaturedUnit( $params = [] ) {
		if ( empty( $params ) ) {
			// No Params = Get one random featured unit
			$data = json_decode( $this->call( 'featuredunit' ) );

			return $this->loadTheme( 'vrpFeaturedUnit', $data );
		}

		if ( count( $params ) == 1 && isset( $params['show'] ) ) {
			// 'show' param = get multiple random featured units
			$data = json_decode( $this->call( 'getfeaturedunits/' . $params['show'] ) );

			return $this->loadTheme( 'vrpFeaturedUnits', $data );
		}

		if ( isset( $params['field'] ) && isset( $params['value'] ) ) {
			// if Field AND Value exist find a custom featured unit
			if ( isset( $params['show'] ) ) {
				// Returning Multiple units
				$params['num'] = $params['show'];
				unset( $params['show'] );
				$data = json_decode( $this->call( 'getfeaturedbyoption', $params ) );

				return $this->loadTheme( 'vrpFeaturedUnits', $data );
			}
			// Returning a single unit
			$params['num'] = 1;
			$data          = json_decode( $this->call( 'getfeaturedbyoption', $params ) );

			return $this->loadTheme( 'vrpFeaturedUnit', $data );
		}

	}

	//
	// Wordpress Admin Methods
	//
	/**
	 * Display notice for user to enter their VRPc API key.
	 */
	public function notice() {
		$siteurl = admin_url( 'admin.php?page=VRPConnector' );
		echo '<div class="updated fade"><b>Vacation Rental Platform</b>: <a href="' . esc_url( $siteurl ) . '">Please enter your API key.</a></div>';
	}

	/**
	 * Admin nav menu items
	 */
	public function setupPage() {
		add_options_page(
			'Settings Admin',
			'VRPConnector',
			'activate_plugins',
			'VRPConnector',
			[ $this, 'settingsPage' ]
		);
	}

	public function registerSettings() {
		register_setting( 'VRPConnector', 'vrpAPI' );
		register_setting( 'VRPConnector', 'vrpUser' );
		register_setting( 'VRPConnector', 'vrpPass' );
		register_setting( 'VRPConnector', 'vrpTheme' );
		add_settings_section( 'vrpApiKey', 'VRP API Key', [ $this, 'apiKeySettingTitleCallback' ], 'VRPConnector' );
		add_settings_field( 'vrpApiKey', 'VRP Api Key', [ $this, 'apiKeyCallback' ], 'VRPConnector', 'vrpApiKey' );
		add_settings_section( 'vrpLoginCreds', 'VRP Login', [ $this, 'vrpLoginSettingTitleCallback' ], 'VRPConnector' );
		add_settings_field( 'vrpUser', 'VRP Username', [ $this, 'vrpUserCallback' ], 'VRPConnector', 'vrpLoginCreds' );
		add_settings_field( 'vrpPass', 'VRP Password', [ $this, 'vrpPasswordCallback' ], 'VRPConnector', 'vrpLoginCreds' );
		add_settings_section( 'vrpTheme', 'VRP Theme Selection', [ $this, 'vrpThemeSettingTitleCallback' ], 'VRPConnector' );
		add_settings_field( 'vrpTheme', 'VRP Theme', [ $this, 'vrpThemeSettingCallback' ], 'VRPConnector', 'vrpTheme' );
	}

	public function apiKeySettingTitleCallback() {
		echo "<p>Your API Key can be found in the settings section after logging in to <a href='https://www.gueststream.net'>Gueststream.net</a>.</p>
        <p>Don't have an account? <a href='http://www.gueststream.com/apps-and-tools/vrpconnector-sign-up-page/'>Click Here</a> to learn more about getting a <a href='https://www.gueststream.net'>Gueststream.net</a> account.</p>
        <p>Demo API Key: <strong>1533020d1121b9fea8c965cd2c978296</strong> The Demo API Key does not contain bookable units therfor availability searches will not work.</p>";
	}

	public function apiKeyCallback() {
		echo '<input type="text" name="vrpAPI" value="' . esc_attr( get_option( 'vrpAPI' ) ) . '" style="width:400px;"/>';
	}

	public function vrpLoginSettingTitleCallback() {
		echo "<p>The VRP Login is only necessary if you want to be able to automatically login to your VRP portal at <a href='https://www.gueststream.net'>Gueststream.net</a>.  The only necessary field in this form is the VRP Api Key above.</p>";
	}

	public function vrpUserCallback() {
		echo '<input type="text" name="vrpUser" value="' . esc_attr( get_option( 'vrpUser' ) ) . '" style="width:400px;"/>';
	}

	public function vrpPasswordSettingTitleCallback() {
	}

	public function vrpPasswordCallback() {
		echo '<input type="password" name="vrpPass" value="' . esc_attr( get_option( 'vrpPass' ) ) . '" style="width:400px;"/>';
	}

	public function vrpThemeSettingTitleCallback() {
	}

	public function vrpThemeSettingCallback() {
		echo '<select name="vrpTheme">';
		foreach ( $this->available_themes as $name => $displayname ) {
			$sel = '';
			if ( $name == $this->themename ) {
				$sel = 'SELECTED';
			}
			echo '<option value="' . esc_attr( $name ) . '" ' . esc_attr( $sel ) . '>' . esc_attr( $displayname ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Displays the 'VRP API Code Entry' admin page
	 */
	public function settingsPage() {
		include VRP_PATH . 'views/settings.php';
	}

	/**
	 * Checks if API Key is good and API is available.
	 *
	 * @return mixed
	 */
	public function testAPI() {
		return json_decode( $this->call( 'testAPI' ) );
	}

	/**
	 * Generates the admin automatic login url.
	 *
	 * @param $email
	 * @param $password
	 *
	 * @return array|mixed
	 */
	public function doLogin( $email, $password ) {
		$url = $this->api_url . $this->api_key . "/userlogin/?email=$email&password=$password";

		return json_decode( file_get_contents( $url ) );
	}

	/**
	 * Checks to see if the page loaded is a VRP page.
	 * Formally $_GET['action'].
	 *
	 * @global WP_Query $wp_query
	 * @return bool
	 */
	public function is_vrp_page() {
		global $wp_query;
		if ( isset( $wp_query->query_vars['action'] ) ) { // Is VRP page.
			$this->action = $wp_query->query_vars['action'];

			return true;
		}

		return false;
	}

	public function remove_filters() {
		if ( $this->is_vrp_page() ) {
			remove_filter( 'the_content', 'wptexturize' );
			remove_filter( 'the_content', 'wpautop' );
		}
	}

	//
	// Data Processing Methods
	//
	private function prepareData() {
		$this->setFavorites();
		$this->prepareSearchData();
	}

	public function prepareSearchResults( $data ) {
		foreach ( $data->results as $key => $unit ) {
			if ( strlen( $unit->Thumb ) == 0 ) {
				// Replacing non-existent thumbnails w/full size Photo URL
				$unit->Thumb = $unit->Photo;
			}
			$data->results[ $key ] = $unit;
		}

		return $data;
	}

	private function prepareSearchData() {
		$this->search = new \stdClass();

		// Arrival
		if ( isset( $_GET['search']['arrival'] ) ) {
			$_SESSION['arrival'] = $_GET['search']['arrival'];
		}

		if ( isset( $_SESSION['arrival'] ) ) {
			$this->search->arrival = date( 'm/d/Y', strtotime( $_SESSION['arrival'] ) );
		} else {
			$this->search->arrival = date( 'm/d/Y', strtotime( '+1 Days' ) );
		}

		// Departure
		if ( isset( $_GET['search']['departure'] ) ) {
			$_SESSION['depart'] = $_GET['search']['departure'];
		}

		if ( isset( $_SESSION['depart'] ) ) {
			$this->search->depart = date( 'm/d/Y', strtotime( $_SESSION['depart'] ) );
		} else {
			$this->search->depart = date( 'm/d/Y', strtotime( '+4 Days' ) );
		}

		// Nights
		if ( isset( $_GET['search']['nights'] ) ) {
			$_SESSION['nights'] = $_GET['search']['nights'];
		}

		if ( isset( $_SESSION['nights'] ) ) {
			$this->search->nights = $_SESSION['nights'];
		} else {
			$this->search->nights = ( strtotime( $this->search->depart ) - strtotime( $this->search->arrival ) ) / 60 / 60 / 24;
		}

		$this->search->type = '';
		if ( isset( $_GET['search']['type'] ) ) {
			$_SESSION['type'] = $_GET['search']['type'];
		}

		if ( isset( $_SESSION['type'] ) ) {
			$this->search->type    = $_SESSION['type'];
			$this->search->complex = $_SESSION['type'];
		}

		// Sleeps
		$this->search->sleeps = '';
		if ( isset( $_GET['search']['sleeps'] ) ) {
			$_SESSION['sleeps'] = $_GET['search']['sleeps'];
		}

		if ( isset( $_SESSION['sleeps'] ) ) {
			$this->search->sleeps = $_SESSION['sleeps'];
		} else {
			$this->search->sleeps = false;
		}

		// Location
		$this->search->location = '';
		if ( isset( $_GET['search']['location'] ) ) {
			$_SESSION['location'] = $_GET['search']['location'];
		}

		if ( isset( $_SESSION['location'] ) ) {
			$this->search->location = $_SESSION['location'];
		} else {
			$this->search->location = false;
		}

		// Bedrooms
		$this->search->bedrooms = '';
		if ( isset( $_GET['search']['bedrooms'] ) ) {
			$_SESSION['bedrooms'] = $_GET['search']['bedrooms'];
		}

		if ( isset( $_SESSION['bedrooms'] ) ) {
			$this->search->bedrooms = $_SESSION['bedrooms'];
		} else {
			$this->search->bedrooms = false;
		}

		// Bathrooms
		if ( isset( $_GET['search']['bathrooms'] ) ) {
			$_SESSION['bathrooms'] = $_GET['search']['bathrooms'];
		}

		if ( isset( $_SESSION['bathrooms'] ) ) {
			$this->search->bathrooms = $_SESSION['bathrooms'];
		} else {
			$this->search->bathrooms = false;
		}

		// Adults
		if ( ! empty( $_GET['search']['Adults'] ) ) {
			$_SESSION['adults'] = (int) $_GET['search']['Adults'];
		}

		if ( isset( $_GET['search']['adults'] ) ) {
			$_SESSION['adults'] = (int) $_GET['search']['adults'];
		}

		if ( isset( $_GET['obj']['Adults'] ) ) {
			$_SESSION['adults'] = (int) $_GET['obj']['Adults'];
		}

		if ( isset( $_SESSION['adults'] ) ) {
			$this->search->adults = $_SESSION['adults'];
		} else {
			$this->search->adults = 2;
		}

		// Children
		if ( isset( $_GET['search']['children'] ) ) {
			$_SESSION['children'] = $_GET['search']['children'];
		}

		if ( isset( $_SESSION['children'] ) ) {
			$this->search->children = $_SESSION['children'];
		} else {
			$this->search->children = 0;
		}

	}
}
