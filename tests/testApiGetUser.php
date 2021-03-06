<?php
/**
 * Contains Class TestApiGetUser.
 *
 * @package WP-Auth0
 *
 * @since 3.11.0
 */

/**
 * Class TestApiGetUser.
 * Test the WP_Auth0_Api_Get_User class.
 */
class TestApiGetUser extends WP_Auth0_Test_Case {

	use HttpHelpers;

	/**
	 * WP_Auth0_Api_Client_Credentials instance.
	 *
	 * @var WP_Auth0_Api_Client_Credentials
	 */
	protected static $api_client_creds;

	/**
	 * Run before the test suite.
	 */
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		self::$api_client_creds = new WP_Auth0_Api_Client_Credentials( self::$opts );
	}

	/**
	 * Test that pre-API calls fail.
	 */
	public function testThatPreflightChecksAreMade() {
		$get_user_api = new WP_Auth0_Api_Get_User( self::$opts, self::$api_client_creds );

		// Should fail with a missing user_id.
		$this->assertNull( $get_user_api->call() );

		// Should fail if no existing API token.
		$this->assertNull( $get_user_api->call( uniqid() ) );
	}

	/**
	 * Test that the API request is made correctly.
	 */
	public function testThatApiRequestIsCorrect() {
		$this->startHttpHalting();

		set_transient( WP_Auth0_Api_Client_Credentials::TOKEN_TRANSIENT_KEY, '__test_access_token__', 9999 );
		set_transient( WP_Auth0_Api_Client_Credentials::SCOPE_TRANSIENT_KEY, 'read:users', 9990 );

		self::$opts->set( 'domain', 'test.auth0.com' );
		$get_user_api = new WP_Auth0_Api_Get_User( self::$opts, self::$api_client_creds );

		try {
			$http_data = [];
			$get_user_api->call( '__test_user_id__' );
		} catch ( Exception $e ) {
			$http_data = unserialize( $e->getMessage() );
		}

		$this->assertEquals( 'https://test.auth0.com/api/v2/users/__test_user_id__', $http_data['url'] );
		$this->assertEquals(
			WP_Auth0_Api_Abstract::get_info_headers()['Auth0-Client'],
			$http_data['headers']['Auth0-Client']
		);
		$this->assertEquals( 'Bearer __test_access_token__', $http_data['headers']['Authorization'] );
		$this->assertEquals( 'GET', $http_data['method'] );
	}

	/**
	 * Test that a network error (caught by WP and returned as a WP_Error) is handled properly.
	 */
	public function testThatNetworkErrorIsHandled() {
		$this->startHttpMocking();
		$this->http_request_type = 'wp_error';

		set_transient( WP_Auth0_Api_Client_Credentials::TOKEN_TRANSIENT_KEY, '__test_access_token__', 9999 );
		set_transient( WP_Auth0_Api_Client_Credentials::SCOPE_TRANSIENT_KEY, 'read:users', 9990 );

		self::$opts->set( 'domain', 'test.auth0.com' );
		$get_user_api = new WP_Auth0_Api_Get_User( self::$opts, self::$api_client_creds );

		$this->assertNull( $get_user_api->call( '__test_user_id__' ) );

		$log = self::$error_log->get();
		$this->assertCount( 1, $log );
		$this->assertEquals( 'WP_Auth0_Api_Get_User::call', $log[0]['section'] );
	}
}
