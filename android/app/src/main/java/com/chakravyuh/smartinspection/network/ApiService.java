package com.chakravyuh.smartinspection.network;

import com.chakravyuh.smartinspection.data.model.Inspection;
import com.chakravyuh.smartinspection.data.model.Tender;
import com.chakravyuh.smartinspection.data.model.User;
import java.util.List;
import java.util.Map;
import okhttp3.MultipartBody;
import okhttp3.RequestBody;
import okhttp3.ResponseBody;
import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.DELETE;
import retrofit2.http.GET;
import retrofit2.http.Multipart;
import retrofit2.http.POST;
import retrofit2.http.Part;
import retrofit2.http.Path;
import retrofit2.http.Query;

public interface ApiService {
    // Health Check
    @GET("health")
    Call<Map<String, Object>> getHealth();

    // Hierarchy & Official Oversight (TASK 3)
    @GET("hierarchy")
    Call<Map<String, Object>> getHierarchy(
        @Query("search") String search,
        @Query("designation") String designation,
        @Query("state_id") Integer stateId,
        @Query("district_id") Integer districtId
    );

    // Authentication
    @POST("auth/login")
    Call<Map<String, Object>> login(@Body Map<String, String> credentials);

    @POST("auth/otp/request")
    Call<Map<String, Object>> requestOtp(@Body Map<String, String> request);

    @POST("auth/otp/verify")
    Call<Map<String, Object>> verifyOtp(@Body Map<String, String> request);

    @POST("auth/ngo/register")
    Call<Map<String, Object>> registerNgo(@Body Map<String, Object> ngoData);

    // Tenders
    @GET("tenders")
    Call<Map<String, Object>> getTenders(
        @Query("category") String category,
        @Query("district_id") Integer districtId,
        @Query("status") String status,
        @Query("search") String search
    );

    @GET("tenders/{id}")
    Call<Map<String, Object>> getTenderDetail(@Path("id") int id);

    @POST("tenders")
    Call<Map<String, Object>> createTender(@Body Map<String, Object> tenderData);

    @DELETE("tenders/{id}")
    Call<Map<String, Object>> deleteTender(@Path("id") int id);

    @Multipart
    @POST("tenders/import")
    Call<Map<String, Object>> importTendersCsv(@Part MultipartBody.Part file);

    // Contractor Section (Ongoing projects & status update)
    @GET("contractor/projects")
    Call<Map<String, Object>> getContractorProjects();

    @POST("contractor/projects/{id}/update-progress")
    Call<Map<String, Object>> updateContractorProgress(
        @Path("id") int id,
        @Body Map<String, Object> progressData
    );

    // Inspections & Evidence
    @GET("inspections")
    Call<Map<String, Object>> getInspections(
        @Query("status") String status,
        @Query("tender_id") Integer tenderId
    );

    @GET("inspections/{id}")
    Call<Map<String, Object>> getInspectionDetail(@Path("id") int id);

    @POST("inspections")
    Call<Map<String, Object>> createOrSyncInspection(@Body Map<String, Object> inspectionData);

    @Multipart
    @POST("inspections/{id}/evidence")
    Call<Map<String, Object>> uploadEvidence(
        @Path("id") int inspectionId,
        @Part MultipartBody.Part mediaFile,
        @Part("latitude") RequestBody latitude,
        @Part("longitude") RequestBody longitude,
        @Part("accuracy") RequestBody accuracy,
        @Part("is_mock") RequestBody isMock,
        @Part("device_capture_time") RequestBody deviceCaptureTime
    );

    @POST("inspections/{id}/submit")
    Call<Map<String, Object>> submitInspection(@Path("id") int inspectionId);

    @POST("inspections/{id}/verify")
    Call<Map<String, Object>> verifyInspection(
        @Path("id") int inspectionId,
        @Body Map<String, Object> verifyData
    );

    @POST("inspections/{id}/close")
    Call<Map<String, Object>> closeInspection(
        @Path("id") int inspectionId,
        @Body Map<String, Object> closeData
    );

    // Complaints & Escalations
    @POST("complaints")
    Call<Map<String, Object>> raiseComplaint(@Body Map<String, Object> complaintData);

    @POST("complaints/{id}/escalate")
    Call<Map<String, Object>> escalateComplaint(
        @Path("id") int complaintId,
        @Body Map<String, Object> escalationData
    );

    @POST("payments/confirm")
    Call<Map<String, Object>> confirmPayment(@Body Map<String, Object> paymentData);

    // Public Nudge
    @POST("nudges")
    Call<Map<String, Object>> submitNudge(@Body Map<String, String> nudgeData);

    // Reports & Downloads
    @GET("reports/inspection/{id}/pdf")
    Call<ResponseBody> downloadInspectionPdf(@Path("id") int id);

    @GET("reports/tenders/csv")
    Call<ResponseBody> exportTendersCsv();

    // Notifications
    @GET("notifications")
    Call<Map<String, Object>> getNotifications();

    @POST("notifications/{id}/read")
    Call<Map<String, Object>> markNotificationRead(@Path("id") int id);

    @GET("notifications/unread-count")
    Call<Map<String, Object>> getUnreadNotificationCount();

    // NGO Approval
    @GET("ngos")
    Call<Map<String, Object>> getNgos(@Query("status") String status);

    @POST("ngos/{id}/decide")
    Call<Map<String, Object>> decideNgo(@Path("id") int id, @Body Map<String, Object> decisionData);

    // Complaints Resolution
    @GET("complaints")
    Call<Map<String, Object>> getComplaints(@Query("tender_id") Integer tenderId);

    @POST("complaints/{id}/resolve")
    Call<Map<String, Object>> resolveComplaint(@Path("id") int id, @Body Map<String, Object> resolveData);

    @POST("complaints/{id}/decide-escalation")
    Call<Map<String, Object>> decideEscalation(@Path("id") int id, @Body Map<String, Object> decideData);

    // Dashboard
    @GET("dashboard/summary")
    Call<Map<String, Object>> getDashboardSummary();
}
