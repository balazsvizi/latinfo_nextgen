<?php
/**
 * Google Drive mentés – alkalmazás szintű beállítások (config.local.php minta).
 * A felhasználói token NEM ide kerül – azt a mentés oldalon Google-bejelentkezéssel kapod.
 *
 * Másold a kulcsokat a nextgen/core/config.local.php fájlba.
 */
return array(
	// OAuth Web kliens (Cloud Console) – ez az alkalmazás, nem a felhasználó
	'GOOGLE_DRIVE_OAUTH_CLIENT_ID' => '123456789-xxxxx.apps.googleusercontent.com',
	'GOOGLE_DRIVE_OAUTH_CLIENT_SECRET' => 'GOCSPX-...',

	// VibeBackup mappa (ugyanaz, mint Civilsziget)
	'GOOGLE_DRIVE_BACKUP_FOLDER_ID' => '1BOBSMtZDB10LWKNcJxDWtq6AnFx4W9mJ',
);
