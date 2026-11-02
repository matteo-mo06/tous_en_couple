<?php
// Schéma initial. Les JSON sont stockés en TEXT (lisibles par SQLite et MySQL).
return [
    "CREATE TABLE IF NOT EXISTS players (
        id {pk},
        login VARCHAR(190) NOT NULL UNIQUE,
        name VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'player',
        active INTEGER NOT NULL DEFAULT 1,
        must_change INTEGER NOT NULL DEFAULT 0,
        home_place VARCHAR(190) NULL,
        joined_year INTEGER NULL,
        start_global DOUBLE NULL,
        created_at VARCHAR(19) NOT NULL
    ) {engine}",

    "CREATE TABLE IF NOT EXISTS login_attempts (
        id {pk},
        login VARCHAR(190) NOT NULL,
        ip VARCHAR(64) NOT NULL,
        attempted_at VARCHAR(19) NOT NULL
    ) {engine}",

    // Une version du barème : l'ensemble des valeurs, en JSON. Un amendement adopté en crée une nouvelle.
    "CREATE TABLE IF NOT EXISTS rule_versions (
        id {pk},
        label VARCHAR(190) NOT NULL,
        effective_year INTEGER NOT NULL,
        params TEXT NOT NULL,
        note TEXT NULL,
        created_at VARCHAR(19) NOT NULL
    ) {engine}",

    // Une année de jeu = une réunion. status : open (on déclare) | soiree (la réunion a lieu) | closed (points calculés)
    "CREATE TABLE IF NOT EXISTS game_years (
        id {pk},
        year INTEGER NOT NULL UNIQUE,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        meeting_date VARCHAR(10) NULL,
        meeting_place VARCHAR(190) NULL,
        host_id INTEGER NULL,
        rule_version_id INTEGER NOT NULL,
        created_at VARCHAR(19) NOT NULL,
        closed_at VARCHAR(19) NULL
    ) {engine}",

    // Les joueurs de l'année et ce qui s'est passé à la réunion (présence, retard, partenaire, distance)
    "CREATE TABLE IF NOT EXISTS year_players (
        id {pk},
        year_id INTEGER NOT NULL,
        player_id INTEGER NOT NULL,
        present INTEGER NULL,
        late INTEGER NOT NULL DEFAULT 0,
        partner_presence VARCHAR(20) NOT NULL DEFAULT 'present',
        travel_band INTEGER NOT NULL DEFAULT 0,
        UNIQUE (year_id, player_id)
    ) {engine}",

    // Tout ce qu'un joueur déclare : relation, étape, rupture, tromperie, rupture cachée, bluff, tentative, absence
    "CREATE TABLE IF NOT EXISTS entries (
        id {pk},
        year_id INTEGER NOT NULL,
        player_id INTEGER NOT NULL,
        type VARCHAR(30) NOT NULL,
        data TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        decision_note TEXT NULL,
        created_by INTEGER NOT NULL,
        created_at VARCHAR(19) NOT NULL,
        decided_by INTEGER NULL,
        decided_at VARCHAR(19) NULL
    ) {engine}",
    "CREATE INDEX idx_entries_year_player ON entries (year_id, player_id)",

    // Journal : qui a fait quoi sur une entrée, et quand
    "CREATE TABLE IF NOT EXISTS entry_log (
        id {pk},
        entry_id INTEGER NOT NULL,
        actor_id INTEGER NOT NULL,
        action VARCHAR(30) NOT NULL,
        note TEXT NULL,
        created_at VARCHAR(19) NOT NULL
    ) {engine}",

    "CREATE TABLE IF NOT EXISTS accusations (
        id {pk},
        year_id INTEGER NOT NULL,
        accuser_id INTEGER NOT NULL,
        target_id INTEGER NOT NULL,
        created_at VARCHAR(19) NOT NULL,
        UNIQUE (year_id, accuser_id, target_id)
    ) {engine}",

    // Votes de tout genre : absence (motif accepté ?), bons termes, prix, amendement
    "CREATE TABLE IF NOT EXISTS votes (
        id {pk},
        kind VARCHAR(20) NOT NULL,
        ref_id INTEGER NOT NULL,
        voter_id INTEGER NOT NULL,
        choice VARCHAR(20) NOT NULL,
        created_at VARCHAR(19) NOT NULL,
        UNIQUE (kind, ref_id, voter_id)
    ) {engine}",

    "CREATE TABLE IF NOT EXISTS prizes (
        id {pk},
        year_id INTEGER NOT NULL,
        label VARCHAR(190) NOT NULL,
        winner_id INTEGER NULL
    ) {engine}",

    // year_id = année de la réunion où l'amendement est voté
    "CREATE TABLE IF NOT EXISTS amendments (
        id {pk},
        year_id INTEGER NOT NULL,
        proposer_id INTEGER NOT NULL,
        title VARCHAR(190) NOT NULL,
        body TEXT NOT NULL,
        param_key VARCHAR(60) NULL,
        param_value TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        applied_version_id INTEGER NULL,
        created_at VARCHAR(19) NOT NULL,
        decided_at VARCHAR(19) NULL
    ) {engine}",

    // Photographie figée d'une année clôturée (jamais recalculée)
    "CREATE TABLE IF NOT EXISTS year_results (
        id {pk},
        year_id INTEGER NOT NULL,
        player_id INTEGER NOT NULL,
        participated INTEGER NOT NULL DEFAULT 1,
        rank_pos INTEGER NOT NULL,
        score INTEGER NOT NULL,
        breakdown TEXT NOT NULL,
        notes TEXT NOT NULL,
        global_before DOUBLE NOT NULL,
        global_points INTEGER NOT NULL,
        global_after DOUBLE NOT NULL,
        global_rank INTEGER NOT NULL,
        state TEXT NOT NULL,
        UNIQUE (year_id, player_id)
    ) {engine}",

    "CREATE TABLE IF NOT EXISTS year_summary (
        year_id INTEGER PRIMARY KEY,
        gifts TEXT NOT NULL,
        cycle_winners TEXT NULL,
        applied_amendments TEXT NULL
    ) {engine}",
];
