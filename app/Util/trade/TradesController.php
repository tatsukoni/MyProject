<?php

$row = 67;

$stateGroup = TradeState::STATE_GROUPS_FOR_ADMIN[$stateGroupId];

// test
// $row = 192, 

// TradesRequest
// $row = 34
$tradeGroupIds = array_keys(TradeState::STATE_GROUPS_FOR_ADMIN);

// trade.php
// $row = 275
// return $proposedPrice->proposed_price;