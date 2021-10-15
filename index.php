<?php

/**
 * @defgroup plugins_gateways_pubidresolver Pub Ids Resolver Gateway Plugin
 */
 
/**
 * @file plugins/gateways/pubIdResolver/index.php
 *
 * Copyright (c) 2021 Yasiel Pérez Vera
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_gateways_pubidresolver
 * @brief Wrapper for the plugin Resolver gateway using public identifier.
 *
 */

require_once('PubIdResolver.inc.php');

return new PubIdResolver();


