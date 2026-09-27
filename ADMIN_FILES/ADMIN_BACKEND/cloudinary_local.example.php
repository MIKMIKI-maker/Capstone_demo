<?php
// Local-only Cloudinary credentials for plain XAMPP development (no
// Docker, so there's no docker-compose.yml to inject these as real
// environment variables).
//
// 1. Copy this file to "cloudinary_local.php" in this same folder.
// 2. Get your Cloud Name, API Key, and API Secret from
//    https://cloudinary.com/console (free account is enough — the app
//    already caps uploads at 10MB to match the free plan's limit).
// 3. Fill in the three values below.
//
// cloudinary_local.php is gitignored — it will never be committed, so it's
// safe to put real credentials in it.

putenv('CLOUDINARY_CLOUD_NAME=your-cloud-name-here');
putenv('CLOUDINARY_API_KEY=your-api-key-here');
putenv('CLOUDINARY_API_SECRET=your-api-secret-here');
