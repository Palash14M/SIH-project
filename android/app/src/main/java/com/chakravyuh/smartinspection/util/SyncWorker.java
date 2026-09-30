package com.chakravyuh.smartinspection.util;

import android.content.Context;
import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;
import com.chakravyuh.smartinspection.db.AppDatabase;
import com.chakravyuh.smartinspection.db.entity.EvidenceEntity;
import com.chakravyuh.smartinspection.db.entity.InspectionEntity;
import com.chakravyuh.smartinspection.network.ApiClient;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;
import java.io.File;
import java.lang.reflect.Type;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import okhttp3.MediaType;
import okhttp3.MultipartBody;
import okhttp3.RequestBody;
import retrofit2.Response;

public class SyncWorker extends Worker {

    public SyncWorker(@NonNull Context context, @NonNull WorkerParameters workerParams) {
        super(context, workerParams);
    }

    @NonNull
    @Override
    public Result doWork() {
        AppDatabase db = AppDatabase.getInstance(getApplicationContext());
        List<InspectionEntity> pending = db.inspectionDao().getPendingSyncInspections();

        if (pending == null || pending.isEmpty()) {
            return Result.success();
        }

        Gson gson = new Gson();
        boolean hasFailures = false;

        for (InspectionEntity entity : pending) {
            try {
                entity.syncStatus = "UPLOADING";
                db.inspectionDao().updateInspection(entity);

                Type listType = new TypeToken<List<Map<String, Object>>>() {}.getType();
                List<Map<String, Object>> milestones = gson.fromJson(entity.milestoneProgressJson, listType);
                List<Map<String, Object>> qualityChecks = gson.fromJson(entity.qualityChecksJson, listType);

                Map<String, Object> req = new HashMap<>();
                req.put("tender_id", entity.tenderId);
                req.put("actual_spent", entity.actualSpent);
                req.put("spent_basis", entity.spentBasis);
                req.put("remarks", entity.remarks);
                req.put("issue_found", entity.issueFound);
                req.put("severity", entity.severity);
                req.put("milestones", milestones);
                req.put("quality_checks", qualityChecks);

                // Sync to backend API
                Response<Map<String, Object>> res = ApiClient.getApiService().createOrSyncInspection(req).execute();

                if (res.isSuccessful() && res.body() != null) {
                    Map<String, Object> body = res.body();
                    Map<String, Object> data = (Map<String, Object>) body.get("data");
                    int serverId = ((Number) data.get("id")).intValue();

                    entity.serverId = serverId;
                    entity.syncStatus = "SYNCED";
                    entity.syncError = null;
                    db.inspectionDao().updateInspection(entity);

                    // Sync attached evidence files
                    List<EvidenceEntity> evidenceList = db.inspectionDao().getEvidenceForInspection(entity.localId);
                    for (EvidenceEntity ee : evidenceList) {
                        File mediaFile = new File(ee.localFilePath);
                        if (mediaFile.exists()) {
                            RequestBody reqFile = RequestBody.create(MediaType.parse("image/jpeg"), mediaFile);
                            MultipartBody.Part bodyPart = MultipartBody.Part.createFormData("media", mediaFile.getName(), reqFile);
                            RequestBody latPart = RequestBody.create(MediaType.parse("text/plain"), String.valueOf(ee.latitude));
                            RequestBody lngPart = RequestBody.create(MediaType.parse("text/plain"), String.valueOf(ee.longitude));
                            RequestBody accPart = RequestBody.create(MediaType.parse("text/plain"), String.valueOf(ee.accuracy));
                            RequestBody mockPart = RequestBody.create(MediaType.parse("text/plain"), ee.isMock ? "1" : "0");
                            RequestBody timePart = RequestBody.create(MediaType.parse("text/plain"), String.valueOf(ee.deviceCaptureTime / 1000));

                            Response<Map<String, Object>> evRes = ApiClient.getApiService().uploadEvidence(
                                serverId, bodyPart, latPart, lngPart, accPart, mockPart, timePart
                            ).execute();

                            if (evRes.isSuccessful()) {
                                ee.syncStatus = "SYNCED";
                                db.inspectionDao().updateEvidence(ee);
                            }
                        }
                    }
                } else {
                    entity.syncStatus = "FAILED";
                    entity.syncError = "Server returned " + res.code();
                    db.inspectionDao().updateInspection(entity);
                    hasFailures = true;
                }

            } catch (Exception e) {
                entity.syncStatus = "FAILED";
                entity.syncError = e.getMessage();
                db.inspectionDao().updateInspection(entity);
                hasFailures = true;
            }
        }

        return hasFailures ? Result.retry() : Result.success();
    }
}
