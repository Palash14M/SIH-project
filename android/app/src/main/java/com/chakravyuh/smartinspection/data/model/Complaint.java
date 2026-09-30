package com.chakravyuh.smartinspection.data.model;

import com.google.gson.annotations.SerializedName;

public class Complaint {
    @SerializedName("id")
    private int id;

    @SerializedName("tender_id")
    private int tenderId;

    @SerializedName("tender_number")
    private String tenderNumber;

    @SerializedName("tender_title")
    private String tenderTitle;

    @SerializedName("ngo_id")
    private int ngoId;

    @SerializedName("ngo_name")
    private String ngoName;

    @SerializedName("subject")
    private String subject;

    @SerializedName("description")
    private String description;

    @SerializedName("current_level")
    private String currentLevel; // SENIOR_OFFICER, DISTRICT_OFFICER, STATE_OFFICER, MOSJE_ADMIN

    @SerializedName("current_assignee_id")
    private int currentAssigneeId;

    @SerializedName("current_assignee_name")
    private String currentAssigneeName;

    @SerializedName("status")
    private String status; // PENDING, RESOLVED, REJECTED, ESCALATED

    @SerializedName("resolution_remarks")
    private String resolutionRemarks;

    @SerializedName("created_at")
    private String createdAt;

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getTenderId() { return tenderId; }
    public void setTenderId(int tenderId) { this.tenderId = tenderId; }

    public String getTenderNumber() { return tenderNumber; }
    public void setTenderNumber(String tenderNumber) { this.tenderNumber = tenderNumber; }

    public String getTenderTitle() { return tenderTitle; }
    public void setTenderTitle(String tenderTitle) { this.tenderTitle = tenderTitle; }

    public int getNgoId() { return ngoId; }
    public void setNgoId(int ngoId) { this.ngoId = ngoId; }

    public String getNgoName() { return ngoName; }
    public void setNgoName(String ngoName) { this.ngoName = ngoName; }

    public String getSubject() { return subject; }
    public void setSubject(String subject) { this.subject = subject; }

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }

    public String getCurrentLevel() { return currentLevel; }
    public void setCurrentLevel(String currentLevel) { this.currentLevel = currentLevel; }

    public int getCurrentAssigneeId() { return currentAssigneeId; }
    public void setCurrentAssigneeId(int currentAssigneeId) { this.currentAssigneeId = currentAssigneeId; }

    public String getCurrentAssigneeName() { return currentAssigneeName; }
    public void setCurrentAssigneeName(String currentAssigneeName) { this.currentAssigneeName = currentAssigneeName; }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public String getResolutionRemarks() { return resolutionRemarks; }
    public void setResolutionRemarks(String resolutionRemarks) { this.resolutionRemarks = resolutionRemarks; }

    public String getCreatedAt() { return createdAt; }
    public void setCreatedAt(String createdAt) { this.createdAt = createdAt; }
}
