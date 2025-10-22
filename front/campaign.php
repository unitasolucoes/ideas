<?php

include('../../../inc/includes.php');
require_once __DIR__ . '/../inc/detail_template.php';

$tickets_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

PluginIdeasDetailTemplate::render($tickets_id, 'campaign');
