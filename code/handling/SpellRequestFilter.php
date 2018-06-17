<?php

/**
 * @deprecated 1.1 Use SpellCheckAdminExtension instead
 */
class SpellRequestFilter implements RequestFilter {

	/**
	 * HtmlEditorConfig name to use
	 *
	 * @var string
	 * @config
	 */
	private static $editor = 'cms';

	public function preRequest(\SS_HTTPRequest $request, \Session $session, \DataModel $model) {
		return true;
	}

	public function postRequest(\SS_HTTPRequest $request, \SS_HTTPResponse $response, \DataModel $model) {
		return true;
	}
}
