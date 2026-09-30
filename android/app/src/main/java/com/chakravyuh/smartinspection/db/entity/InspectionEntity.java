package com.chakravyuh.smartinspection.db.entity;

import androidx.room.Entity;
import androidx.room.PrimaryKey;

@Entity(tableName = "offline_inspections")
public class InspectionEntity {
    @PrimaryKey(autoGenerate = true)
    public long localId;

    public Integer serverId; // null if pending upload
    public int tenderId;
    public String tenderNumber;
    public String tenderTitle;
    public double overallProgress;
    public double actualSpent;
    public String spentBasis;
    public String spentDocLocalPath;
    public String remarks;
    public boolean issueFound;
    public String severity;
    public String qualityChecksJson; // JSON string of checks
    public String milestoneProgressJson; // JSON string of milestone progress
    public String syncStatus; // PENDING, UPLOADING, SYNCED, FAILED
    public String syncError;
    public long createdAt;
}
