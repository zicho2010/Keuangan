-- Migration: Add status column to transaksi_file table for approval workflow
-- Run this SQL to add approval status to file uploads

ALTER TABLE `transaksi_file` 
ADD COLUMN `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER `uploaded_at`;



