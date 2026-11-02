<?php
// Journal personnel : événements et moments marquants (sans effet sur les points).
return [
    "CREATE TABLE IF NOT EXISTS journal (
        id {pk},
        player_id INTEGER NOT NULL,
        year_id INTEGER NOT NULL,
        kind VARCHAR(20) NOT NULL,
        title VARCHAR(190) NOT NULL,
        note TEXT NULL,
        start_date VARCHAR(10) NOT NULL,
        end_date VARCHAR(10) NULL,
        shared INTEGER NOT NULL DEFAULT 1,
        created_at VARCHAR(19) NOT NULL
    ) {engine}",
];
