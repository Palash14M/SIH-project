package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Toast;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import com.chakravyuh.smartinspection.SmartInspectionApp;
import com.chakravyuh.smartinspection.util.PreferenceManager;

public abstract class BaseFragment extends Fragment {
    protected PreferenceManager preferenceManager;

    @Override
    public void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        preferenceManager = SmartInspectionApp.getInstance().getPreferenceManager();
    }

    protected void showToast(String message) {
        if (getContext() != null) {
            Toast.makeText(getContext(), message, Toast.LENGTH_SHORT).show();
        }
    }

    protected void showError(String message) {
        showToast(message);
    }
}
