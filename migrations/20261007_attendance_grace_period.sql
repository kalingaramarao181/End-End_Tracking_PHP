-- 9:30 AM Eastern shift: on time through 10:00:00 AM.
ALTER TABLE attendance_settings ALTER COLUMN grace_minutes SET DEFAULT 30;
START TRANSACTION;
UPDATE attendance_settings SET grace_minutes=30 WHERE id=1;
-- Reclassify historical punches; do not change times, hours or work status.
UPDATE attendance a JOIN attendance_settings s ON s.id=1
SET a.status=CASE WHEN a.time_in<=ADDTIME(s.work_start,SEC_TO_TIME(s.grace_minutes*60)) THEN 1 ELSE 0 END
WHERE a.time_in IS NOT NULL AND a.time_in<>'00:00:00'
AND a.work_status IN ('working','completed','half_day');
COMMIT;
