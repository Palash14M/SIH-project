package com.chakravyuh.smartinspection.data.model;

import com.google.gson.annotations.SerializedName;

public class User {
    @SerializedName("id")
    private int id;

    @SerializedName("name")
    private String name;

    @SerializedName("email")
    private String email;

    @SerializedName("phone")
    private String phone;

    @SerializedName("role")
    private String role;

    @SerializedName("district_id")
    private Integer districtId;

    @SerializedName("state_id")
    private Integer stateId;

    @SerializedName("status")
    private String status;

    public int getId() { return id; }
    public String getName() { return name; }
    public String getEmail() { return email; }
    public String getPhone() { return phone; }
    public String getRole() { return role; }
    public Integer getDistrictId() { return districtId; }
    public Integer getStateId() { return stateId; }
    public String getStatus() { return status; }
}
