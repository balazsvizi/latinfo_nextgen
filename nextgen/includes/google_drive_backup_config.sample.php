<?php
/**
 * Másold a fájlt ide (ne legyen a repóban): secret_folder/google_drive_backup_config.php
 *
 * === A) Személyes Gmail Saját meghajtó (VibeBackup mappa) ===
 * Service account NEM tud ide írni. OAuth refresh kell:
 * 1) Cloud Console: OAuth kliens (Web) + Drive API
 * 2) Admin → Google Drive mentés → OAuth beállítás → bejelentkezés
 * 3) A rendszer automatikusan menti a secret_folder mappába
 *
 * return array(
 *     'auth' => 'oauth_refresh',
 *     'folder_id' => '1BOBSMtZDB10LWKNcJxDWtq6AnFx4W9mJ',
 *     'oauth_client_id' => '....apps.googleusercontent.com',
 *     'oauth_client_secret' => 'GOCSPX-...',
 *     'oauth_refresh_token' => '1//...',
 * );
 *
 * === B) Google Workspace Csapatmeghajtó (Shared Drive) ===
 *
 * return array(
 *     'auth' => 'service_account',
 *     'json_path' => __DIR__ . '/your-service-account.json',
 *     'folder_id' => '1BOBSMtZDB10LWKNcJxDWtq6AnFx4W9mJ',
 * );
 *
 * Környezeti változók: GOOGLE_DRIVE_BACKUP_FOLDER_ID, GOOGLE_DRIVE_BACKUP_AUTH,
 * GOOGLE_DRIVE_OAUTH_CLIENT_ID, GOOGLE_DRIVE_OAUTH_CLIENT_SECRET, GOOGLE_DRIVE_OAUTH_REFRESH_TOKEN
 */
return array(
	'auth' => 'oauth_refresh',
	'folder_id' => '1BOBSMtZDB10LWKNcJxDWtq6AnFx4W9mJ',
);
