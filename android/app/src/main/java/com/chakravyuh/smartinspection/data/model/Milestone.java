package com.chakravyuh.smartinspection.data.model;

import com.google.gson.annotations.SerializedName;

public class Milestone {
    @SerializedName("id")
    private int id;

    @SerializedName("tender_id")
    private int tenderId;

    @SerializedName("name")
    private String name;

    @SerializedName("planned_date")
    private String plannedDate;

    @SerializedName("planned_weight")
    private double plannedWeight; // percentage

    @SerializedName("actual_progress")
    private double actualProgress; // percentage

    public int getId() { return id; }
    public int getTenderId() { return tenderId; }
    public String getName() { return name; }
    public String getPlannedDate() { return plannedDate; }
    public double getPlannedWeight() { return plannedWeight; }
    public double getActualProgress() { return actualProgress; }
    public void setActualProgress(double actualProgress) { this.actualProgress = actualProgress; }
}
