package com.chakravyuh.smartinspection.db.dao;

import androidx.room.Dao;
import androidx.room.Insert;
import androidx.room.Query;
import androidx.room.Update;
import com.chakravyuh.smartinspection.db.entity.EvidenceEntity;
import com.chakravyuh.smartinspection.db.entity.InspectionEntity;
import java.util.List;

@Dao
public interface InspectionDao {
    @Insert
    long insertInspection(InspectionEntity inspection);

    @Update
    void updateInspection(InspectionEntity inspection);

    @Query("SELECT * FROM offline_inspections ORDER BY createdAt DESC")
    List<InspectionEntity> getAllOfflineInspections();

    @Query("SELECT * FROM offline_inspections WHERE syncStatus = 'PENDING' OR syncStatus = 'FAILED'")
    List<InspectionEntity> getPendingSyncInspections();

    @Insert
    long insertEvidence(EvidenceEntity evidence);

    @Query("SELECT * FROM offline_evidence WHERE localInspectionId = :inspectionId")
    List<EvidenceEntity> getEvidenceForInspection(long inspectionId);

    @Query("SELECT * FROM offline_evidence WHERE syncStatus = 'PENDING'")
    List<EvidenceEntity> getPendingSyncEvidence();

    @Update
    void updateEvidence(EvidenceEntity evidence);
}
