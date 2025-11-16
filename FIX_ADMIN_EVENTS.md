# Fix Admin Events Panel Errors

## Problem
The admin events.php page is showing errors because:
1. Missing file: `templates/admin_header.php` ✅ FIXED
2. Missing database columns in the `events` table ⚠️ NEEDS FIX

## Solution

### Step 1: Import Database Fix (REQUIRED)
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select the `spark_platform` database
3. Click on "SQL" tab
4. Copy and paste the contents of `database_fix_events.sql`
5. Click "Go" button

### Step 2: Verify Fix
After running the SQL fix, refresh the admin events page:
```
http://localhost/spark/admin/events.php
```

## What Was Fixed
✅ Created `templates/admin_header.php` file
⚠️ Need to add missing columns to events table:
- `status` (upcoming/ongoing/completed/cancelled)
- `start_date`, `end_date`, `start_time`, `end_time`
- `image_url`, `short_description`, `event_type`
- `venue`, `external_link`, `featured`
- `requirements`, `perks`, `price`

## Alternative: Quick MySQL Commands
If you prefer command line, you can run:
```sql
USE spark_platform;
SOURCE database_fix_events.sql;
```

## Notes
- The original schema.sql has different column names than what the admin panel expects
- This fix adds the missing columns without removing the old ones
- Your existing event data will be preserved
