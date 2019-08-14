<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$installer = $this;

$installer->startSetup();


$installer->addAttribute("customer", "longtoken", array(
    'label' => 'MasterPass Long Access Token',
    'type' => 'varchar',
    'input' => 'text',
    'default' => '',
    'position' => 70,
    'visible' => true,
    'required' => false,
    'user_defined' => true,
    'searchable' => false,
    'filterable' => false,
    'comparable' => false,
    'visible_on_front' => false,
    'unique' => false
));



$installer->endSetup();
