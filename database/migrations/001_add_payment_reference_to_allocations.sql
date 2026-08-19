-- Migration: add payment_reference to allocations
-- Run this SQL on your database to add the optional column used by the app.

ALTER TABLE allocations
  ADD COLUMN payment_reference VARCHAR(100) DEFAULT NULL;
