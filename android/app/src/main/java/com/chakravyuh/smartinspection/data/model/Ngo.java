package com.chakravyuh.smartinspection.data.model;

import com.google.gson.annotations.SerializedName;

public class Ngo {
    @SerializedName("id")
    private int id;

    @SerializedName("user_id")
    private int userId;

    @SerializedName("organization_name")
    private String organizationName;

    @SerializedName("registration_number")
    private String registrationNumber;

    @SerializedName("contact_person")
    private String contactPerson;

    @SerializedName("phone")
    private String phone;

    @SerializedName("email")
    private String email;

    @SerializedName("address")
    private String address;

    @SerializedName("district_id")
    private int districtId;

    @SerializedName("district_name")
    private String districtName;

    @SerializedName("status")
    private String status; // PENDING, APPROVED, REJECTED

    @SerializedName("rejection_reason")
    private String rejectionReason;

    @SerializedName("credential_document_path")
    private String credentialDocumentPath;

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getUserId() { return userId; }
    public void setUserId(int userId) { this.userId = userId; }

    public String getOrganizationName() { return organizationName; }
    public void setOrganizationName(String organizationName) { this.organizationName = organizationName; }

    public String getRegistrationNumber() { return registrationNumber; }
    public void setRegistrationNumber(String registrationNumber) { this.registrationNumber = registrationNumber; }

    public String getContactPerson() { return contactPerson; }
    public void setContactPerson(String contactPerson) { this.contactPerson = contactPerson; }

    public String getPhone() { return phone; }
    public void setPhone(String phone) { this.phone = phone; }

    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }

    public String getAddress() { return address; }
    public void setAddress(String address) { this.address = address; }

    public int getDistrictId() { return districtId; }
    public void setDistrictId(int districtId) { this.districtId = districtId; }

    public String getDistrictName() { return districtName; }
    public void setDistrictName(String districtName) { this.districtName = districtName; }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public String getRejectionReason() { return rejectionReason; }
    public void setRejectionReason(String rejectionReason) { this.rejectionReason = rejectionReason; }

    public String getCredentialDocumentPath() { return credentialDocumentPath; }
    public void setCredentialDocumentPath(String credentialDocumentPath) { this.credentialDocumentPath = credentialDocumentPath; }
}
