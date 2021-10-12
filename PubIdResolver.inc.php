<?php

/**
 * @file plugins/gateways/pubIdResolver/PubIdResolver.inc.php
 *
 * Copyright (c) 2021 Yasiel Perez Vera
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PubIdResolver
 * @ingroup plugins_gateways_pubidresolver
 *
 * @brief Simple pub ids resolver gateway plugin
 */

import('lib.pkp.classes.plugins.GatewayPlugin');

class PubIdResolver extends GatewayPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Get the name of the settings file to be installed on new journal
	 * creation.
	 * @return string
	 */
	function getContextSpecificPluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'pubIdResolver';
	}

	function getDisplayName() {
		return __('plugins.gateways.pubIdResolver.displayName');
	}

	function getDescription() {
		return __('plugins.gateways.pubIdResolver.description');
	}

	/**
	 * Handle fetch requests for this plugin.
	 */
	function fetch($args, $request) {
		if (!$this->getEnabled()) {
			return false;
		}
		$journal = $request->getJournal();			
		$journalId = $journal->getId();	
		$pubId = implode('/', $args);
		$pubIdPlugins = (array) PluginRegistry::loadCategory('pubIds', true, $journalId);
        foreach ($pubIdPlugins as $pubIdPlugin) {
			$pubIdType = $pubIdPlugin->getPubIdType();
			
			$DAOs = DAORegistry::getDAOs();
			$issueDAO = $DAOs['IssueDAO'];
			$articleGalleyDAO = $DAOs['ArticleGalleyDAO'];
			$article = false;
			
			if (array_key_exists('SubmissionDAO', $DAOs)){
				$submissionDAO = $DAOs['SubmissionDAO'];
				$article = $submissionDAO->getByPubId($pubIdType, $pubId, $journalId);
			}
			if (array_key_exists('PublishedArticleDAO', $DAOs)){
				$publishedArticleDAO = $DAOs['PublishedArticleDAO'];
				$article = $publishedArticleDAO->getPublishedArticleByPubId($pubIdType, $pubId, $journalId);
			}		
			if($article) {
				$request->redirect(null, 'article', 'view', $article->getId());
				break;
			}
			else {
				$issue = $issueDAO->getByPubId($pubIdType, $pubId, $journalId);
				if($issue)
				{
					$request->redirect(null, 'issue', 'view', $issue->getId());
					break;
				}
				else {
					$submissionGalley = $articleGalleyDAO->getGalleyByPubId($pubIdType, $pubId);
					if($submissionGalley){
						$publicationId = $submissionGalley->_data['publicationId'] ? $submissionGalley->_data['publicationId'] : $submissionGalley->_data['submissionId'];
						$request->redirect(null, 'article', 'view',[$publicationId, $submissionGalley->getBestGalleyId()]);
						break;
					}
				}
			}			
		}

		// Failure.
		header('HTTP/1.0 404 Not Found');
		$templateMgr = TemplateManager::getManager($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);
		$templateMgr->assign('message', 'plugins.gateways.pubIdResolver.errors.errorMessage');
		$templateMgr->display('frontend/pages/message.tpl');
		exit;
	}
}


