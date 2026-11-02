<?php
// Demande de changement de nom (validée par l'administrateur).
return [
    "ALTER TABLE players ADD COLUMN pending_name VARCHAR(190) NULL",
];
