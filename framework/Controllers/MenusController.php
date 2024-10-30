<?php

namespace KRV\Controllers;

class MenusController {
	private $_capability = 'administrator';
	public $pages = [];

	public function __construct()
	{
		$this->pages();
	}

	public function getPages()
	{
		return $this->pages;
	}

	public function pages()
	{
		$this->pages = [
			[
				'title'      => KRV_FULL_NAME,
				'is_submenu' => false,
			],
			[
				'title'      => 'Dashboard',
				'is_submenu' => true,
				'path'       => $this->generateMenuSlug( 'Dashboard' ),
			],
			[
				'title'      => 'Reports',
				'is_submenu' => true,
				'path'       => $this->generateMenuSlug( 'Reports' ),
			],
			//[
			//	'title'      => 'Settings',
			//	'is_submenu' => true,
			//	'path'       => $this->generateMenuSlug( 'Settings' ),
			//],
		];

		return $this->pages;
	}

	public function init()
	{
		foreach ( $this->pages() as $index => $menu ) {
			if ( false === $menu['is_submenu'] ) {
				add_menu_page(
					$this->generateMenuPageTitle( $menu['title'] ),
					$menu['title'],
					$this->_capability,
					$this->pages()[1]['path'],
					[ $this, 'menuView' ],
					'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNzMuNzUgMjczLjc1Ij48cGF0aCBmaWxsPSIjNzJhZWU2IiBkPSJNMCAxMEMwLTMgMjAtMyAyMCAxMHYyNDRoMjQ0YzEzIDAgMTMgMjAgMCAyMEgxMGMtNSAwLTEwLTQtMTAtMTBWMTB6Ij48L3BhdGg+PHBhdGggZmlsbD0iIzcyYWVlNiIgZD0iTTY5IDIxOWMtOCAxMS0yNC0xLTE2LTEybDQ3LTYzYzktMTEgMjItMTcgMzctMTZsMzAgNGM3IDEgMTMtMiAxOC04bDQyLTUxYzktMTEgMjQgMiAxNiAxMmwtNDIgNTJjLTkgMTEtMjMgMTYtMzcgMTVsLTI5LTRjLTgtMS0xNSAyLTE5IDhsLTQ3IDYzeiI+PC9wYXRoPjwvc3ZnPg==',
				);
			} else {
				add_submenu_page(
					$this->pages()[1]['path'],
					$this->generateMenuPageTitle( $menu['title'] ),
					$menu['title'],
					$this->_capability,
					$menu['path'],
					[ $this, 'menuView' ],
				);
			}
		}
	}

	public function menuView()
	{
		echo '<div class="wrap" id="krv-app"></div>';
	}

	private function generateMenuSlug( string $slug, bool $makeSame = false )
	{
		return $makeSame ? KRV_SLUG : strsMakeMenuSlug( $slug, '-' );
	}

	private function generateMenuPageTitle( string $menuTitle )
	{
		return $menuTitle . ' &#8767; ' . KRV_FULL_NAME;
	}
}
