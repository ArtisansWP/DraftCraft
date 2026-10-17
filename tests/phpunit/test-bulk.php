<?php
/**
 * Tests for DraftCraft_Bulk queue helpers.
 *
 * @package DraftCraft
 */

/**
 * Class DraftCraft_Test_Bulk
 */
class DraftCraft_Test_Bulk extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		DraftCraft_Bulk::save_queue( array() );
	}

	public function test_peek_next_skips_future_and_failed() {
		DraftCraft_Bulk::save_queue(
			array(
				array(
					'id'             => 'future',
					'keyword'        => 'future',
					'status'         => 'pending',
					'scheduled_date' => '2099-01-01',
				),
				array(
					'id'             => 'failed',
					'keyword'        => 'failed',
					'status'         => 'failed',
					'scheduled_date' => '2000-01-01',
				),
				array(
					'id'             => 'due',
					'keyword'        => 'due-now',
					'status'         => 'pending',
					'scheduled_date' => '2000-01-01',
				),
			)
		);
		$next = DraftCraft_Bulk::peek_next();
		$this->assertSame( 'due-now', $next['keyword'] );
		$this->assertSame( 2, $next['_queue_index'] );
	}

	public function test_complete_item_reindexes_and_fires_action() {
		DraftCraft_Bulk::save_queue(
			array(
				array(
					'id'      => 'a',
					'keyword' => 'one',
					'status'  => 'pending',
				),
				array(
					'id'      => 'b',
					'keyword' => 'two',
					'status'  => 'pending',
				),
			)
		);
		$fired = array();
		add_action(
			'draftcraft_bulk_item_completed',
			static function ( $row, $post_id ) use ( &$fired ) {
				$fired[] = array( $row['keyword'], $post_id );
			},
			10,
			2
		);
		DraftCraft_Bulk::complete_item( 0, 99 );
		$queue = DraftCraft_Bulk::get_queue();
		$this->assertCount( 1, $queue );
		$this->assertSame( 'two', $queue[0]['keyword'] );
		$this->assertSame( array( array( 'one', 99 ) ), $fired );
		DraftCraft_Bulk::complete_item( 9, 1 );
		$this->assertCount( 1, DraftCraft_Bulk::get_queue() );
	}

	public function test_fail_item_keeps_row() {
		DraftCraft_Bulk::save_queue(
			array(
				array(
					'id'      => 'a',
					'keyword' => 'one',
					'status'  => 'pending',
				),
			)
		);
		DraftCraft_Bulk::fail_item( 0, '<b>no credits</b>' );
		$row = DraftCraft_Bulk::get_queue()[0];
		$this->assertSame( 'failed', $row['status'] );
		$this->assertSame( 'no credits', $row['fail_reason'] );
		$this->assertNotEmpty( $row['failed_at'] );
		DraftCraft_Bulk::fail_item( 5, 'missing' );
		$this->assertCount( 1, DraftCraft_Bulk::get_queue() );
	}

	public function test_build_prompt_from_row() {
		$empty_cat = DraftCraft_Bulk::build_prompt_from_row(
			array(
				'keyword'      => 'router',
				'instructions' => 'Keep it short',
				'category_id'  => 0,
			)
		);
		$this->assertSame( 'router', $empty_cat['focus_keyword'] );
		$this->assertStringContainsString( 'router', $empty_cat['system_extra'] );
		$this->assertStringContainsString( 'router', $empty_cat['user_extra'] );
		$this->assertStringContainsString( 'Keep it short', $empty_cat['system_extra'] );
		$this->assertSame( array(), $empty_cat['category_ids'] );

		$with_cat = DraftCraft_Bulk::build_prompt_from_row(
			array(
				'keyword'     => 'router',
				'category_id' => 12,
			)
		);
		$this->assertSame( array( 12 ), $with_cat['category_ids'] );
	}

	public function test_map_headers_aliases() {
		$ref = new ReflectionMethod( DraftCraft_Bulk::class, 'map_headers' );
		$ref->setAccessible( true );
		$map = $ref->invoke( null, array( 'KW', 'Cat', 'Notes', 'Publish Date' ) );
		$this->assertSame( 0, $map['keyword'] );
		$this->assertSame( 1, $map['category'] );
		$this->assertSame( 2, $map['instructions'] );
		$this->assertSame( 3, $map['scheduled'] );
	}
}
