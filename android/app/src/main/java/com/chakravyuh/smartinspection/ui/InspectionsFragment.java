package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AlertDialog;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.network.ApiClient;
import com.chakravyuh.smartinspection.util.Constants;
import com.google.android.material.chip.Chip;
import com.google.android.material.chip.ChipGroup;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class InspectionsFragment extends BaseFragment {
    private ChipGroup chipGroupFilter;
    private Chip chipAll, chipInProgress, chipFlagged, chipCompleted;
    private LinearLayout layoutTenderList;
    private TextView tvEmptyList;

    private List<Map<String, Object>> allTenders = new ArrayList<>();
    private String currentFilter = "ALL";

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View root = inflater.inflate(R.layout.fragment_inspections, container, false);
        initViews(root);
        setupFilters();
        loadTenders();
        return root;
    }

    private void initViews(View v) {
        chipGroupFilter = v.findViewById(R.id.chipGroupFilter);
        chipAll = v.findViewById(R.id.chipAll);
        chipInProgress = v.findViewById(R.id.chipInProgress);
        chipFlagged = v.findViewById(R.id.chipFlagged);
        chipCompleted = v.findViewById(R.id.chipCompleted);
        layoutTenderList = v.findViewById(R.id.layoutTenderList);
        tvEmptyList = v.findViewById(R.id.tvEmptyList);
    }

    private void setupFilters() {
        chipAll.setOnClickListener(v -> applyFilter("ALL"));
        chipInProgress.setOnClickListener(v -> applyFilter("IN_PROGRESS"));
        chipFlagged.setOnClickListener(v -> applyFilter("FLAGGED"));
        chipCompleted.setOnClickListener(v -> applyFilter("COMPLETED"));
    }

    private void applyFilter(String filter) {
        currentFilter = filter;
        renderFilteredTenders();
    }

    public void filterBySearch(String query) {
        if (allTenders.isEmpty()) return;
        List<Map<String, Object>> matched = new ArrayList<>();
        String q = query.toLowerCase().trim();
        for (Map<String, Object> t : allTenders) {
            String num = String.valueOf(t.get("tender_number")).toLowerCase();
            String title = String.valueOf(t.get("title")).toLowerCase();
            String cat = String.valueOf(t.get("category_name")).toLowerCase();
            if (num.contains(q) || title.contains(q) || cat.contains(q)) {
                matched.add(t);
            }
        }
        renderItems(matched);
    }

    private void loadTenders() {
        tvEmptyList.setVisibility(View.VISIBLE);
        tvEmptyList.setText("Loading public tender catalog...");

        ApiClient.getApiService().getTenders(null, null, null, null).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    List<Map<String, Object>> data = (List<Map<String, Object>>) body.get("data");
                    if (data != null) {
                        allTenders = data;
                        renderFilteredTenders();
                        return;
                    }
                }
                tvEmptyList.setText("No public tenders available.");
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                tvEmptyList.setText("Network error loading tenders: " + t.getMessage());
            }
        });
    }

    private void renderFilteredTenders() {
        if (allTenders.isEmpty()) {
            tvEmptyList.setVisibility(View.VISIBLE);
            tvEmptyList.setText("No tenders found.");
            layoutTenderList.removeAllViews();
            return;
        }

        List<Map<String, Object>> filtered = new ArrayList<>();
        for (Map<String, Object> t : allTenders) {
            String status = (String) t.get("status");
            Boolean hasVariance = (Boolean) t.get("variance_flag");
            Boolean isDelayed = (Boolean) t.get("delay_flag");

            if ("ALL".equalsIgnoreCase(currentFilter)) {
                filtered.add(t);
            } else if ("IN_PROGRESS".equalsIgnoreCase(currentFilter) && "IN_PROGRESS".equalsIgnoreCase(status)) {
                filtered.add(t);
            } else if ("FLAGGED".equalsIgnoreCase(currentFilter) && (Boolean.TRUE.equals(hasVariance) || Boolean.TRUE.equals(isDelayed))) {
                filtered.add(t);
            } else if ("COMPLETED".equalsIgnoreCase(currentFilter) && "COMPLETED".equalsIgnoreCase(status)) {
                filtered.add(t);
            }
        }

        renderItems(filtered);
    }

    private void renderItems(List<Map<String, Object>> items) {
        layoutTenderList.removeAllViews();
        if (items.isEmpty()) {
            tvEmptyList.setVisibility(View.VISIBLE);
            tvEmptyList.setText("No matching tenders found under selected filter.");
            return;
        }

        tvEmptyList.setVisibility(View.GONE);
        String role = preferenceManager.getUserRole();
        boolean isPublic = (role == null || role.isEmpty() || "PUBLIC".equalsIgnoreCase(role));

        for (Map<String, Object> t : items) {
            View card = LayoutInflater.from(getContext()).inflate(R.layout.item_tender_card, layoutTenderList, false);

            TextView tvNumber = card.findViewById(R.id.tvTenderNumber);
            TextView tvTitle = card.findViewById(R.id.tvTenderTitle);
            TextView tvCategory = card.findViewById(R.id.tvTenderCategory);
            TextView tvStatus = card.findViewById(R.id.tvTenderStatus);
            TextView tvProgress = card.findViewById(R.id.tvProgressPercent);
            TextView tvTimeRemaining = card.findViewById(R.id.tvTimeRemaining);
            TextView tvVariance = card.findViewById(R.id.tvVarianceBadge);

            View layoutStaffDetails = card.findViewById(R.id.layoutStaffDetails);
            TextView tvContractorDetails = card.findViewById(R.id.tvContractorDetails);
            TextView tvInspectorDetails = card.findViewById(R.id.tvInspectorDetails);
            TextView tvBudgetDetails = card.findViewById(R.id.tvBudgetDetails);

            String number = (String) t.get("tender_number");
            String title = (String) t.get("title");
            String category = (String) t.get("category_name");
            String status = (String) t.get("status");
            Number progress = (Number) t.get("overall_progress_pct");
            if (progress == null) progress = (Number) t.get("progress_percentage");
            Boolean hasVariance = (Boolean) t.get("variance_flag");
            Boolean isDelayed = (Boolean) t.get("delay_flag");
            String timeRemaining = (String) t.get("time_remaining_label");

            tvNumber.setText(number);
            tvTitle.setText(title);
            tvCategory.setText(category != null ? category : "Public Works");
            tvStatus.setText(status != null ? status : "IN_PROGRESS");
            tvProgress.setText(String.format("Progress: %.0f%%", progress != null ? progress.doubleValue() : 0.0));

            if (tvTimeRemaining != null) {
                if (timeRemaining != null && !timeRemaining.isEmpty()) {
                    tvTimeRemaining.setText("⏱ " + timeRemaining);
                } else {
                    tvTimeRemaining.setText("⏱ In Progress");
                }
            }

            if (Boolean.TRUE.equals(hasVariance)) {
                tvVariance.setVisibility(View.VISIBLE);
                tvVariance.setText("⚠ VARIANCE > 10%");
            } else if (Boolean.TRUE.equals(isDelayed)) {
                tvVariance.setVisibility(View.VISIBLE);
                tvVariance.setText("⏱ DELAYED");
            } else {
                tvVariance.setVisibility(View.GONE);
            }

            // TASK 2: Role-aware contact & budget visibility
            if (isPublic) {
                if (layoutStaffDetails != null) layoutStaffDetails.setVisibility(View.GONE);
            } else {
                if (layoutStaffDetails != null) {
                    layoutStaffDetails.setVisibility(View.VISIBLE);

                    String contractorName = (String) t.get("contractor_name");
                    String contractorPhone = (String) t.get("contractor_phone");
                    String contractorEmail = (String) t.get("contractor_email");
                    String inspName = (String) t.get("assigned_inspector_name");
                    String inspPhone = (String) t.get("assigned_inspector_phone");
                    Number sanctioned = (Number) t.get("sanctioned_amount");
                    Number spent = (Number) t.get("actual_spent");

                    if (tvContractorDetails != null) {
                        String cText = "Contractor: " + (contractorName != null ? contractorName : "Assigned Firm");
                        if (contractorPhone != null && !contractorPhone.isEmpty()) {
                            cText += " • 📞 " + contractorPhone;
                        }
                        tvContractorDetails.setText(cText);
                    }

                    if (tvInspectorDetails != null) {
                        String iText = "Field Inspector: " + (inspName != null ? inspName : "Rajesh M.");
                        if (inspPhone != null && !inspPhone.isEmpty()) {
                            iText += " • 📞 " + inspPhone;
                        }
                        tvInspectorDetails.setText(iText);
                    }

                    if (tvBudgetDetails != null) {
                        double sAmt = (sanctioned != null) ? sanctioned.doubleValue() : 45000000.0;
                        double aAmt = (spent != null) ? spent.doubleValue() : 36500000.0;
                        tvBudgetDetails.setText(String.format("Budget: ₹%,.0f | Spent: ₹%,.0f", sAmt, aAmt));
                    }
                }
            }

            // Master Admin project deletion before completion
            View btnMasterDelete = card.findViewById(R.id.btnMasterDelete);
            if (btnMasterDelete != null) {
                boolean isMasterAdmin = Constants.ROLE_MASTER_ADMIN.equalsIgnoreCase(role);
                double progVal = (progress != null) ? progress.doubleValue() : 0.0;
                boolean isNotComplete = !"COMPLETED".equalsIgnoreCase(status) && progVal < 100.0;

                if (isMasterAdmin && isNotComplete) {
                    btnMasterDelete.setVisibility(View.VISIBLE);
                    btnMasterDelete.setOnClickListener(v -> {
                        Number idNum = (Number) t.get("id");
                        if (idNum != null) {
                            confirmDeleteProject(idNum.intValue(), number, title);
                        }
                    });
                } else {
                    btnMasterDelete.setVisibility(View.GONE);
                }
            }

            card.setOnClickListener(v -> {
                Number idNum = (Number) t.get("id");
                if (idNum != null) {
                    showToast("Tender #" + number + " selected (ID: " + idNum.intValue() + ")");
                }
            });

            layoutTenderList.addView(card);
        }
    }

    private void confirmDeleteProject(int tenderId, String tenderNumber, String tenderTitle) {
        new AlertDialog.Builder(requireContext())
            .setTitle("⚠️ Delete Incomplete Project")
            .setMessage("Are you sure you want to permanently delete project " + tenderNumber + " (" + tenderTitle + ") before completion?\n\nThis will remove all associated milestones, inspections, and issues. This action cannot be undone.")
            .setPositiveButton("Delete Permanently", (dialog, which) -> {
                ApiClient.getApiService().deleteTender(tenderId).enqueue(new Callback<Map<String, Object>>() {
                    @Override
                    public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                        if (response.isSuccessful() && response.body() != null) {
                            Map<String, Object> body = response.body();
                            if (Boolean.TRUE.equals(body.get("success"))) {
                                showToast("✓ Project deleted successfully before completion.");
                                loadTenders();
                                return;
                            }
                        }
                        showError("Failed to delete project. Check server permissions.");
                    }

                    @Override
                    public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                        showError("Server error: " + t.getMessage());
                    }
                });
            })
            .setNegativeButton("Cancel", null)
            .show();
    }
}
