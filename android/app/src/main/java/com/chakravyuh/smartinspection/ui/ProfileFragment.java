package com.chakravyuh.smartinspection.ui;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AlertDialog;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.util.Constants;

public class ProfileFragment extends BaseFragment {
    private TextView tvAvatarInitials;
    private TextView tvProfileName;
    private TextView tvProfileRole;
    private TextView tvProfileJurisdiction;
    private TextView tvSyncStatus;
    private Button btnLogout;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View root = inflater.inflate(R.layout.fragment_profile, container, false);
        initViews(root);
        renderUserProfile();
        setupEvents();
        return root;
    }

    private void initViews(View v) {
        tvAvatarInitials = v.findViewById(R.id.tvAvatarInitials);
        tvProfileName = v.findViewById(R.id.tvProfileName);
        tvProfileRole = v.findViewById(R.id.tvProfileRole);
        tvProfileJurisdiction = v.findViewById(R.id.tvProfileJurisdiction);
        tvSyncStatus = v.findViewById(R.id.tvSyncStatus);
        btnLogout = v.findViewById(R.id.btnLogout);
    }

    private void renderUserProfile() {
        String name = preferenceManager.getUserName();
        String role = preferenceManager.getUserRole();
        Integer districtId = preferenceManager.getUserDistrictId();

        tvProfileName.setText(name != null && !name.isEmpty() ? name : "Official User");
        tvProfileRole.setText(role != null ? role.toUpperCase() : "PUBLIC CITIZEN");

        // Compute initials
        String initials = "IN";
        if (name != null && !name.isEmpty()) {
            String[] parts = name.trim().split("\\s+");
            if (parts.length >= 2) {
                initials = ("" + parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
            } else if (parts.length == 1 && parts[0].length() > 0) {
                initials = parts[0].substring(0, Math.min(2, parts[0].length())).toUpperCase();
            }
        }
        tvAvatarInitials.setText(initials);

        // Jurisdiction
        if (districtId != null && districtId > 0) {
            String distName = districtId == 1 ? "Nagpur, Maharashtra" :
                             districtId == 2 ? "Pune, Maharashtra" :
                             districtId == 3 ? "Lucknow, Uttar Pradesh" : "Varanasi, Uttar Pradesh";
            tvProfileJurisdiction.setText("Jurisdiction: " + distName);
        } else if (Constants.ROLE_STATE_OFFICER.equalsIgnoreCase(role)) {
            tvProfileJurisdiction.setText("Jurisdiction: State of Maharashtra & Uttar Pradesh");
        } else if (Constants.ROLE_MOSJE_ADMIN.equalsIgnoreCase(role)) {
            tvProfileJurisdiction.setText("Jurisdiction: National (All States & UTs)");
        } else {
            tvProfileJurisdiction.setText("Public Citizen Transparency Access");
        }

        tvSyncStatus.setText("All records synced (0 in queue)");
    }

    private void setupEvents() {
        btnLogout.setOnClickListener(v -> {
            new AlertDialog.Builder(requireContext())
                .setTitle("Confirm Sign Out")
                .setMessage("Are you sure you want to end your active session?")
                .setPositiveButton("Sign Out", (dialog, which) -> performLogout())
                .setNegativeButton("Cancel", null)
                .show();
        });
    }

    private void performLogout() {
        preferenceManager.clearSession();
        Intent intent = new Intent(requireActivity(), LoginActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(intent);
        requireActivity().finish();
    }
}
