-- Add dive_site column to divelogs table
ALTER TABLE divelogs ADD COLUMN dive_site VARCHAR(255) COMMENT 'Name of the specific dive site';

-- Create index for faster searching
CREATE INDEX idx_dive_site ON divelogs(dive_site);

-- Update existing records with location as dive_site if dive_site is NULL
UPDATE divelogs SET dive_site = location WHERE dive_site IS NULL;
