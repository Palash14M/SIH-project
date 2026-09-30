package com.chakravyuh.smartinspection.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class Inspection {
    @SerializedName("id")
    private int id;

    @SerializedName("tender_id")
    private int tenderId;

    @SerializedName("tender_number")
    private String tenderNumber;

    @SerializedName("tender_title")
    private String tenderTitle;

    @SerializedName("inspector_id")
    private int inspectorId;

    @SerializedName("inspector_name")
    private String inspectorName;

    @SerializedName("status")
    private String status;

    @SerializedName("overall_progress")
    private double overallProgress;

    @SerializedName("actual_spent_recorded")
    private double actualSpentRecorded;

    @SerializedName("spent_basis")
    private String spentBasis;

    @SerializedName("spent_document_photo")
    private String spentDocumentPhoto;

    @SerializedName("remarks")
    private String remarks;

    @SerializedName("issue_found")
    private boolean issueFound;

    @SerializedName("severity")
    private String severity; // LOW, MEDIUM, HIGH, CRITICAL

    @SerializedName("officer_remarks")
    private String officerRemarks;

    @SerializedName("created_at")
    private String createdAt;

    @SerializedName("submitted_at")
    private String submittedAt;

    @SerializedName("verified_at")
    private String verifiedAt;

    @SerializedName("is_reinspection")
    private boolean isReinspection;

    @SerializedName("quality_checks")
    private List<QualityCheck> qualityChecks;

    public int getId() { return id; }
    public int getTenderId() { return tenderId; }
    public String getTenderNumber() { return tenderNumber; }
    public String getTenderTitle() { return tenderTitle; }
    public String getStatus() { return status; }
    public double getOverallProgress() { return overallProgress; }
    public double getActualSpentRecorded() { return actualSpentRecorded; }
    public String getSpentBasis() { return spentBasis; }
    public String getRemarks() { return remarks; }
    public boolean isIssueFound() { return issueFound; }
    public String getSeverity() { return severity; }
    public String getOfficerRemarks() { return officerRemarks; }
    public String getCreatedAt() { return createdAt; }
    public boolean isReinspection() { return isReinspection; }
    public List<QualityCheck> getQualityChecks() { return qualityChecks; }
}
