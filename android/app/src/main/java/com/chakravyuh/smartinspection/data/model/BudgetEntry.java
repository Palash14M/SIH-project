package com.chakravyuh.smartinspection.data.model;

import com.google.gson.annotations.SerializedName;

public class BudgetEntry {
    @SerializedName("id")
    private int id;

    @SerializedName("tender_id")
    private int tenderId;

    @SerializedName("inspection_id")
    private int inspectionId;

    @SerializedName("amount")
    private double amount;

    @SerializedName("basis")
    private String basis;

    @SerializedName("document_photo_path")
    private String documentPhotoPath;

    @SerializedName("status")
    private String status; // ACCEPTED, DISPUTED

    @SerializedName("dispute_reason")
    private String disputeReason;

    @SerializedName("recorded_by_name")
    private String recordedByName;

    @SerializedName("created_at")
    private String createdAt;

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getTenderId() { return tenderId; }
    public void setTenderId(int tenderId) { this.tenderId = tenderId; }

    public int getInspectionId() { return inspectionId; }
    public void setInspectionId(int inspectionId) { this.inspectionId = inspectionId; }

    public double getAmount() { return amount; }
    public void setAmount(double amount) { this.amount = amount; }

    public String getBasis() { return basis; }
    public void setBasis(String basis) { this.basis = basis; }

    public String getDocumentPhotoPath() { return documentPhotoPath; }
    public void setDocumentPhotoPath(String documentPhotoPath) { this.documentPhotoPath = documentPhotoPath; }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public String getDisputeReason() { return disputeReason; }
    public void setDisputeReason(String disputeReason) { this.disputeReason = disputeReason; }

    public String getRecordedByName() { return recordedByName; }
    public void setRecordedByName(String recordedByName) { this.recordedByName = recordedByName; }

    public String getCreatedAt() { return createdAt; }
    public void setCreatedAt(String createdAt) { this.createdAt = createdAt; }
}
