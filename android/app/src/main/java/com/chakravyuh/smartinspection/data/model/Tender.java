package com.chakravyuh.smartinspection.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class Tender {
    @SerializedName("id")
    private int id;

    @SerializedName("tender_number")
    private String tenderNumber;

    @SerializedName("title")
    private String title;

    @SerializedName("category")
    private String category;

    @SerializedName("department")
    private String department;

    @SerializedName("district_name")
    private String districtName;

    @SerializedName("state_name")
    private String stateName;

    @SerializedName("latitude")
    private double latitude;

    @SerializedName("longitude")
    private double longitude;

    @SerializedName("location_note")
    private String locationNote;

    @SerializedName("contractor_name")
    private String contractorName;

    @SerializedName("contractor_registration")
    private String contractorRegistration;

    // Restricted fields (masked for public)
    @SerializedName("contractor_phone")
    private String contractorPhone;

    @SerializedName("contractor_email")
    private String contractorEmail;

    @SerializedName("sanctioned_amount")
    private double sanctionedAmount;

    @SerializedName("actual_spent")
    private double actualSpent;

    @SerializedName("award_date")
    private String awardDate;

    @SerializedName("start_date")
    private String startDate;

    @SerializedName("end_date")
    private String endDate;

    @SerializedName("status")
    private String status;

    @SerializedName("progress_percentage")
    private double progressPercentage;

    @SerializedName("spent_percentage")
    private double spentPercentage;

    @SerializedName("variance")
    private double variance;

    @SerializedName("variance_flag")
    private boolean varianceFlag;

    @SerializedName("delay_flag")
    private boolean delayFlag;

    @SerializedName("responsible_senior_name")
    private String responsibleSeniorName;

    @SerializedName("milestones")
    private List<Milestone> milestones;

    public int getId() { return id; }
    public String getTenderNumber() { return tenderNumber; }
    public String getTitle() { return title; }
    public String getCategory() { return category; }
    public String getDepartment() { return department; }
    public String getDistrictName() { return districtName; }
    public String getStateName() { return stateName; }
    public double getLatitude() { return latitude; }
    public double getLongitude() { return longitude; }
    public String getContractorName() { return contractorName; }
    public String getContractorPhone() { return contractorPhone; }
    public String getContractorEmail() { return contractorEmail; }
    public double getSanctionedAmount() { return sanctionedAmount; }
    public double getActualSpent() { return actualSpent; }
    public String getStartDate() { return startDate; }
    public String getEndDate() { return endDate; }
    public String getStatus() { return status; }
    public double getProgressPercentage() { return progressPercentage; }
    public double getSpentPercentage() { return spentPercentage; }
    public double getVariance() { return variance; }
    public boolean isVarianceFlag() { return varianceFlag; }
    public boolean isDelayFlag() { return delayFlag; }
    public String getResponsibleSeniorName() { return responsibleSeniorName; }
    public List<Milestone> getMilestones() { return milestones; }
}
