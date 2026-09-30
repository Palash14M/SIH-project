package com.chakravyuh.smartinspection.util;

public final class Constants {
    private Constants() {}

    // Base URLs (Permanent Cloud 24/7 Endpoint)
    public static final String DEFAULT_BASE_URL = "https://smart-inspection-mosje.onrender.com/api/";
    public static final String LAN_BASE_URL = "http://172.20.187.147:8000/api/";
    public static final String EMULATOR_BASE_URL = "http://10.0.2.2:8000/api/";

    // Watermark text
    public static final String WATERMARK_SLOGAN = "for the people to the people";

    // GPS accuracy threshold
    public static final float MAX_GPS_ACCURACY_METERS = 100.0f;

    // Financial rules
    public static final double ESCALATION_BASE_FEE = 400.00;
    public static final double ESCALATION_GST_RATE = 0.18; // 18%

    // Roles
    public static final String ROLE_INSPECTOR = "INSPECTOR";
    public static final String ROLE_DISTRICT_OFFICER = "DISTRICT_OFFICER";
    public static final String ROLE_STATE_OFFICER = "STATE_OFFICER";
    public static final String ROLE_MOSJE_ADMIN = "MOSJE_ADMIN";
    public static final String ROLE_MASTER_ADMIN = "MASTER_ADMIN";
    public static final String ROLE_NGO = "NGO";
    public static final String ROLE_CONTRACTOR = "CONTRACTOR";
    public static final String ROLE_PUBLIC = "PUBLIC";

    // Inspection Statuses
    public static final String STATUS_DRAFT = "DRAFT";
    public static final String STATUS_SUBMITTED = "SUBMITTED";
    public static final String STATUS_VERIFIED = "VERIFIED";
    public static final String STATUS_ISSUE_RAISED = "ISSUE_RAISED";
    public static final String STATUS_NOTIFIED = "NOTIFIED";
    public static final String STATUS_IN_RESOLUTION = "IN_RESOLUTION";
    public static final String STATUS_REINSPECTION_PENDING = "REINSPECTION_PENDING";
    public static final String STATUS_CLOSED = "CLOSED";
}
