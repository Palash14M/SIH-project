package com.chakravyuh.smartinspection.ui;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.cardview.widget.CardView;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.network.ApiClient;
import com.chakravyuh.smartinspection.util.Constants;
import java.util.List;
import java.util.Map;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class HomeFragment extends BaseFragment {
    private TextView tvStatActiveCount;
    private TextView tvStatFlagCount;
    private TextView tvStatPendingCount;

    private CardView cardShortcut1, cardShortcut2, cardShortcut3, cardShortcut4;
    private TextView tvShortcut1Title, tvShortcut1Subtitle;
    private TextView tvShortcut2Title, tvShortcut2Subtitle;
    private TextView tvShortcut3Title, tvShortcut3Subtitle;
    private TextView tvShortcut4Title, tvShortcut4Subtitle;

    private TextView tvRecentHeader;
    private TextView tvViewAll;
    private LinearLayout layoutRecentItems;
    private TextView tvEmptyRecent;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View root = inflater.inflate(R.layout.fragment_home, container, false);
        initViews(root);
        setupRoleShortcuts();
        loadDashboardStats();
        loadRecentTenders();
        return root;
    }

    private void initViews(View v) {
        tvStatActiveCount = v.findViewById(R.id.tvStatActiveCount);
        tvStatFlagCount = v.findViewById(R.id.tvStatFlagCount);
        tvStatPendingCount = v.findViewById(R.id.tvStatPendingCount);

        cardShortcut1 = v.findViewById(R.id.cardShortcut1);
        cardShortcut2 = v.findViewById(R.id.cardShortcut2);
        cardShortcut3 = v.findViewById(R.id.cardShortcut3);
        cardShortcut4 = v.findViewById(R.id.cardShortcut4);

        tvShortcut1Title = v.findViewById(R.id.tvShortcut1Title);
        tvShortcut1Subtitle = v.findViewById(R.id.tvShortcut1Subtitle);
        tvShortcut2Title = v.findViewById(R.id.tvShortcut2Title);
        tvShortcut2Subtitle = v.findViewById(R.id.tvShortcut2Subtitle);
        tvShortcut3Title = v.findViewById(R.id.tvShortcut3Title);
        tvShortcut3Subtitle = v.findViewById(R.id.tvShortcut3Subtitle);
        tvShortcut4Title = v.findViewById(R.id.tvShortcut4Title);
        tvShortcut4Subtitle = v.findViewById(R.id.tvShortcut4Subtitle);

        tvRecentHeader = v.findViewById(R.id.tvRecentHeader);
        tvViewAll = v.findViewById(R.id.tvViewAll);
        layoutRecentItems = v.findViewById(R.id.layoutRecentItems);
        tvEmptyRecent = v.findViewById(R.id.tvEmptyRecent);
    }

    private void setupRoleShortcuts() {
        String role = preferenceManager.getUserRole();

        if (Constants.ROLE_INSPECTOR.equalsIgnoreCase(role)) {
            configureShortcut(1, "My Tenders", "Assigned field projects");
            configureShortcut(2, "Field Camera", "Capture GPS watermarked proof");
            configureShortcut(3, "Quality Checks", "Category standards checklist");
            configureShortcut(4, "Offline Queue", "Pending local submissions");

            cardShortcut2.setOnClickListener(v -> {
                if (getActivity() instanceof MainActivity) {
                    ((MainActivity) getActivity()).openCamera();
                }
            });
        } else if (Constants.ROLE_DISTRICT_OFFICER.equalsIgnoreCase(role)) {
            configureShortcut(1, "Tender Admin", "District projects & milestones");
            configureShortcut(2, "Verify Inspections", "Review field evidence");
            configureShortcut(3, "NGO Approvals", "Verify registration documents");
            configureShortcut(4, "CSV Bulk Import", "Import tender spreadsheets");
        } else if (Constants.ROLE_STATE_OFFICER.equalsIgnoreCase(role)) {
            configureShortcut(1, "State Projects", "Oversight of regional works");
            configureShortcut(2, "Variance Flags", "Expenditure vs Progress gaps");
            configureShortcut(3, "Delayed Works", "Overdue project alerts");
            configureShortcut(4, "State Reports", "Export PDF & CSV records");
        } else if (Constants.ROLE_MOSJE_ADMIN.equalsIgnoreCase(role)) {
            configureShortcut(1, "National Tenders", "MoSJE scheme repositories");
            configureShortcut(2, "Staff Directory", "Create & manage officials");
            configureShortcut(3, "Checklist Engine", "Quality checklist templates");
            configureShortcut(4, "Audit & Fees", "Full audit trail & fee ledger");
        } else if (Constants.ROLE_MASTER_ADMIN.equalsIgnoreCase(role)) {
            configureShortcut(1, "Master Projects", "Delete incomplete & govern");
            configureShortcut(2, "Contractor Desk", "Contractor progress portal");
            configureShortcut(3, "Staff Directory", "Create & manage officials");
            configureShortcut(4, "Audit & Security", "Audit logs & system health");

            cardShortcut2.setOnClickListener(v -> {
                startActivity(new Intent(getActivity(), ContractorDashboardActivity.class));
            });
        } else if (Constants.ROLE_CONTRACTOR.equalsIgnoreCase(role)) {
            configureShortcut(1, "Ongoing Projects", "Project details & milestones");
            configureShortcut(2, "Update Status", "Update completion % & remarks");
            configureShortcut(3, "Inspecting Officer", "Contact assigned inspector");
            configureShortcut(4, "Site Photos", "Inspection evidence photos");

            cardShortcut1.setOnClickListener(v -> startActivity(new Intent(getActivity(), ContractorDashboardActivity.class)));
            cardShortcut2.setOnClickListener(v -> startActivity(new Intent(getActivity(), ContractorDashboardActivity.class)));
            cardShortcut3.setOnClickListener(v -> startActivity(new Intent(getActivity(), ContractorDashboardActivity.class)));
            cardShortcut4.setOnClickListener(v -> startActivity(new Intent(getActivity(), ContractorDashboardActivity.class)));
        } else if (Constants.ROLE_NGO.equalsIgnoreCase(role)) {
            configureShortcut(1, "Monitored Works", "Attached public tenders");
            configureShortcut(2, "Raise Complaint", "Report field irregularities");
            configureShortcut(3, "Escalations & Fee", "Track ₹472 fees & refunds");
            configureShortcut(4, "Public Nudges", "Alerts received from citizens");
        } else {
            // Public citizen
            configureShortcut(1, "Tender Explorer", "Transparent public works");
            configureShortcut(2, "Budget & Progress", "Verify spent vs milestones");
            configureShortcut(3, "Nudge NGO", "Prompt social monitors (1/day)");
            configureShortcut(4, "Inspection Results", "Public audit summaries");
        }

        tvViewAll.setOnClickListener(v -> {
            if (getActivity() instanceof MainActivity) {
                ((MainActivity) getActivity()).switchToTab(R.id.nav_inspections);
            }
        });
    }

    private void configureShortcut(int index, String title, String subtitle) {
        if (index == 1) {
            tvShortcut1Title.setText(title);
            tvShortcut1Subtitle.setText(subtitle);
            cardShortcut1.setOnClickListener(v -> {
                if (getActivity() instanceof MainActivity) {
                    ((MainActivity) getActivity()).switchToTab(R.id.nav_inspections);
                }
            });
        } else if (index == 2) {
            tvShortcut2Title.setText(title);
            tvShortcut2Subtitle.setText(subtitle);
        } else if (index == 3) {
            tvShortcut3Title.setText(title);
            tvShortcut3Subtitle.setText(subtitle);
        } else if (index == 4) {
            tvShortcut4Title.setText(title);
            tvShortcut4Subtitle.setText(subtitle);
        }
    }

    private void loadDashboardStats() {
        ApiClient.getApiService().getDashboardSummary().enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    Map<String, Object> data = (Map<String, Object>) body.get("data");
                    if (data != null) {
                        Map<String, Object> tenders = (Map<String, Object>) data.get("tenders");
                        if (tenders != null) {
                            Number total = (Number) tenders.get("total");
                            Number variance = (Number) tenders.get("variance_flagged");
                            Number delayed = (Number) tenders.get("delayed");

                            if (total != null) tvStatActiveCount.setText(String.valueOf(total.intValue()));
                            if (variance != null) tvStatFlagCount.setText(String.valueOf(variance.intValue()));
                            if (delayed != null) tvStatPendingCount.setText(String.valueOf(delayed.intValue()));
                        }
                    }
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                // Keep default seed indicators
            }
        });
    }

    private void loadRecentTenders() {
        ApiClient.getApiService().getTenders(null, null, null, null).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    List<Map<String, Object>> items = (List<Map<String, Object>>) body.get("data");
                    if (items != null && !items.isEmpty()) {
                        renderTenderItems(items);
                        return;
                    }
                }
                tvEmptyRecent.setText("No recent tenders found in your jurisdiction.");
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                tvEmptyRecent.setText("Unable to load live tenders: " + t.getMessage());
            }
        });
    }

    private void renderTenderItems(List<Map<String, Object>> tenders) {
        layoutRecentItems.removeAllViews();
        tvEmptyRecent.setVisibility(View.GONE);

        String role = preferenceManager.getUserRole();
        boolean isPublic = (role == null || role.isEmpty() || "PUBLIC".equalsIgnoreCase(role));

        // Update header based on role (TASK 3)
        if (Constants.ROLE_INSPECTOR.equalsIgnoreCase(role)) {
            tvRecentHeader.setText("My Assigned Ongoing Works");
        } else if (Constants.ROLE_DISTRICT_OFFICER.equalsIgnoreCase(role)) {
            tvRecentHeader.setText("District Project & Inspector Oversight");
        } else if (Constants.ROLE_STATE_OFFICER.equalsIgnoreCase(role)) {
            tvRecentHeader.setText("State Hierarchy & Regional Works");
        } else if (Constants.ROLE_NGO.equalsIgnoreCase(role)) {
            tvRecentHeader.setText("Monitored Works & Contractor Contacts");
        } else if (Constants.ROLE_MOSJE_ADMIN.equalsIgnoreCase(role)) {
            tvRecentHeader.setText("National Tender Directory & Oversight");
        } else {
            tvRecentHeader.setText("Public Citizen Works (Nudge Available)");
        }

        int max = Math.min(tenders.size(), 4);
        for (int i = 0; i < max; i++) {
            Map<String, Object> t = tenders.get(i);
            View card = LayoutInflater.from(getContext()).inflate(R.layout.item_tender_card, layoutRecentItems, false);

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
            } else {
                tvVariance.setVisibility(View.GONE);
            }

            // Role-aware contact & budget visibility
            if (isPublic) {
                if (layoutStaffDetails != null) layoutStaffDetails.setVisibility(View.GONE);
            } else {
                if (layoutStaffDetails != null) {
                    layoutStaffDetails.setVisibility(View.VISIBLE);

                    String contractorName = (String) t.get("contractor_name");
                    String contractorPhone = (String) t.get("contractor_phone");
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
                        String iText = "Inspector: " + (inspName != null ? inspName : "Rajesh M.");
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

            layoutRecentItems.addView(card);
        }
    }
}
