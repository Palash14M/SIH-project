package com.chakravyuh.smartinspection.data.model;

import com.google.gson.annotations.SerializedName;

public class QualityCheck {
    @SerializedName("id")
    private int id;

    @SerializedName("template_id")
    private int templateId;

    @SerializedName("category")
    private String category;

    @SerializedName("item_name")
    private String itemName;

    @SerializedName("is_critical")
    private boolean isCritical;

    @SerializedName("status")
    private String status; // PASS, FAIL, NA

    @SerializedName("remarks")
    private String remarks;

    @SerializedName("evidence_photo_path")
    private String evidencePhotoPath;

    public int getId() { return id; }
    public String getItemName() { return itemName; }
    public boolean isCritical() { return isCritical; }
    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }
    public String getRemarks() { return remarks; }
    public void setRemarks(String remarks) { this.remarks = remarks; }
    public String getEvidencePhotoPath() { return evidencePhotoPath; }
    public void setEvidencePhotoPath(String path) { this.evidencePhotoPath = path; }
}
