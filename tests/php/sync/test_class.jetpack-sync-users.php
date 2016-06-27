<?php


/**
 * Testing CRUD on Users
 */
class WP_Test_Jetpack_New_Sync_Users extends WP_Test_Jetpack_New_Sync_Base {
	protected $user_id;

	public function setUp() {
		parent::setUp();

		// create a user
		$this->user_id = $this->factory->user->create();
		$this->client->do_sync();
	}

	public function test_insert_user_is_synced() {
		$user = get_user_by( 'id', $this->user_id );
		$server_user = $this->server_replica_storage->get_user( $this->user_id );
		// make sure that we don't have a password
		unset( $user->data->user_pass );
		$this->assertFalse(  isset( $server_user->data->user_pass ) );

		// The regular user object doesn't have allowed_mime_types
		unset( $server_user->data->allowed_mime_types );

		$this->assertEqualsObject( $user, $server_user );
	}

	public function test_update_user_url_is_synced() {
		$new_url = 'http://jetpack.com';

		wp_update_user( array(
			'ID' => $this->user_id,
			'user_url' => $new_url
		) );

		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );
		$this->assertEquals( $new_url, $server_user->data->user_url );
	}

	public function test_update_user_password_is_not_synced() {
		$this->server_event_storage->reset();
		$new_password = 'New PassWord';

		wp_update_user( array(
			'ID' => $this->user_id,
			'user_pass' => $new_password
		) );
		$this->client->do_sync();

		// Don't sync the password changes since we don't track password
		$events = $this->server_event_storage->get_all_events();
		$this->assertEmpty( $events );
	}

	public function test_delete_user_is_synced() {
		$user = get_user_by( 'id', $this->user_id );

		$this->client->do_sync();

		// make sure user exists in replica
		$this->assertUsersEqual( $user, $this->server_replica_storage->get_user( $this->user_id ) );

		wp_delete_user( $this->user_id );

		$this->client->do_sync();
		$this->client->do_sync();
		
		$this->assertNull( $this->server_replica_storage->get_user( $this->user_id ) );
	}

	public function test_delete_user_reassign_is_synced() {
		$reassign = $this->factory->user->create();
		wp_delete_user( $this->user_id, $reassign );
		$this->client->do_sync();
		// $this->client->do_sync();

		$event = $this->server_event_storage->get_most_recent_event( 'deleted_user' );
		$this->assertEquals( 'deleted_user', $event->action );
		$this->assertEquals( $this->user_id, $event->args[0] );
		$this->assertEquals( $reassign, $event->args[1] );
	}

	// Roles syncing

	public function test_user_add_role_is_synced() {
		$user = get_user_by( 'id', $this->user_id );
		$user->add_role( 'author' );

		$this->client->do_sync();
		
		$server_user = $this->server_replica_storage->get_user( $this->user_id );
		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );
	}

	public function test_user_set_role_is_synced() {
		$user = get_user_by( 'id', $this->user_id );
		$user->set_role( 'author' );

		$this->client->do_sync();
		$server_user = $this->server_replica_storage->get_user( $this->user_id );

		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );
	}

	public function test_user_remove_role_is_synced() {
		$user = get_user_by( 'id', $this->user_id );
		$user->add_role( 'author' );
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );
		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );

		// lets now remove role
		$user->remove_role( 'author' );
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );

		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );
	}

	// Capabilities syncing
	public function test_user_add_capability_is_synced() {
		$user = get_user_by( 'id', $this->user_id );
		$user->add_cap( 'do_stuff', true );
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );
		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );

		// lets now remove role
		$user->remove_role( 'author' );
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );

		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );
	}

	public function test_user_update_capability_is_synced() {
		$user = get_user_by( 'id', $this->user_id );
		$user->add_cap( 'do_stuff', true );
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );
		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );

		// lets update the capability
		$user->add_cap( 'do_stuff', false );
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );

		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );
	}

	public function test_user_remove_capability_is_synced() {
		$user = get_user_by( 'id', $this->user_id );
		$user->add_cap( 'do_stuff', true );
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );
		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );

		// lets update the capability
		$user->remove_cap( 'do_stuff' );
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );

		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );
		$this->assertUsersEqual( $client_user, $server_user );
	}

	public function test_user_remove_all_capability_is_synced() {
		$user = get_user_by( 'id', $this->user_id );
		$user->add_cap( 'do_stuff', true );
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );
		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );

		$this->assertUsersEqual( $client_user, $server_user );

		// lets update the capability
		$user->remove_all_caps();
		$this->client->do_sync();

		$server_user = $this->server_replica_storage->get_user( $this->user_id );

		$client_user = get_user_by( 'id', $this->user_id );
		unset( $client_user->data->user_pass );

		$this->assertEqualsObject( $client_user, $server_user );
	}
	public function test_sync_allowed_file_type() {
		$server_user_file_mime_types = $this->server_replica_storage->get_allowed_mime_types( $this->user_id );
		$this->assertEquals( get_allowed_mime_types( $this->user_id ), $server_user_file_mime_types );
	}

	// to test run phpunit -c tests/php.multisite.xml --filter test_does_not_sync_non_site_users_in_multisite
	public function test_deletes_users_removed_from_multisite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Run it in multi site mode' );
		}

		$original_blog_id = get_current_blog_id();

		// NOTE this is necessary because WPMU causes certain assumptions about transients
		// to be wrong, and tests to explode. @see: https://github.com/sheabunge/WordPress/commit/ff4f1bb17095c6af8a0f35ac304f79074f3c3ff6
		global $wpdb;

		$suppress = $wpdb->suppress_errors();
		$other_blog_id = wpmu_create_blog( 'foo.com', '', "My Blog", $this->user_id );
		$wpdb->suppress_errors( $suppress );

		$other_blog_user_id = $this->factory->user->create();
		add_user_to_blog( $other_blog_id, $other_blog_user_id, 'administrator' );
		remove_user_from_blog( $other_blog_user_id, $original_blog_id );

		$this->client->do_sync();

		$this->assertNull( $this->server_replica_storage->get_user( $other_blog_user_id ) );
	}

	public function test_syncs_users_added_to_multisite() {
		global $wpdb;

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Run it in multi site mode' );
		}

		$original_blog_id = get_current_blog_id();

		// create a different blog
		$suppress = $wpdb->suppress_errors();
		$other_blog_id = wpmu_create_blog( 'foo.com', '', "My Blog", $this->user_id );
		$wpdb->suppress_errors( $suppress );

		// create a user from within that blog (won't be synced)
		switch_to_blog( $other_blog_id );
		$mu_blog_user_id = $this->factory->user->create();
		restore_current_blog();

		$this->client->do_sync();

		$this->assertNull( $this->server_replica_storage->get_user( $mu_blog_user_id ) );

		add_user_to_blog( $original_blog_id, $mu_blog_user_id, 'administrator' );

		$this->client->do_sync();

		$this->assertNotNull( $this->server_replica_storage->get_user( $mu_blog_user_id ) );
	}

	protected function assertUsersEqual( $user1, $user2 ) {
		// order-independent comparison
		$user1_array = get_object_vars( $user1->data );
		$user2_array = get_object_vars( $user2->data );

		// we don't compare passwords because we don't sync them!
		unset( $user1_array['user_pass'] );
		unset( $user2_array['user_pass'] );

		$this->assertTrue( array_diff( $user1_array, $user2_array ) == array_diff( $user2_array, $user1_array ) );
	}
}

