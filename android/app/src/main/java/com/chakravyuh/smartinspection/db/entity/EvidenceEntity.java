package com.chakravyuh.smartinspection.db.entity;

import androidx.room.Entity;
import androidx.room.PrimaryKey;

@Entity(tableName = "offline_evidence")
public class EvidenceEntity {
    @PrimaryKey(autoGenerate = true)
    public long localId;

    public long localInspectionId;
    public Integer serverEvidenceId;
    public String localFilePath;
    public String mediaType; // PHOTO, VIDEO
    public double latitude;
    public double longitude;
    public float accuracy;
    public boolean isMock;
    public long deviceCaptureTime;
    public String syncStatus; // PENDING, UPLOADING, SYNCED, FAILED
}
