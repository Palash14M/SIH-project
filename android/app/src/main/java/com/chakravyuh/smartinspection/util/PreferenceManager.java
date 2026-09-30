package com.chakravyuh.smartinspection.util;

import android.content.Context;
import android.content.SharedPreferences;

public class PreferenceManager {
    private static final String PREF_NAME = "smart_inspection_prefs";
    private static final String KEY_TOKEN = "jwt_token";
    private static final String KEY_USER_ID = "user_id";
    private static final String KEY_USER_NAME = "user_name";
    private static final String KEY_USER_ROLE = "user_role";
    private static final String KEY_DISTRICT_ID = "district_id";
    private static final String KEY_STATE_ID = "state_id";

    private final SharedPreferences prefs;

    public PreferenceManager(Context context) {
        this.prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
    }

    public void saveSession(String token, int userId, String name, String role, Integer districtId, Integer stateId) {
        prefs.edit()
            .putString(KEY_TOKEN, token)
            .putInt(KEY_USER_ID, userId)
            .putString(KEY_USER_NAME, name)
            .putString(KEY_USER_ROLE, role)
            .putInt(KEY_DISTRICT_ID, districtId != null ? districtId : -1)
            .putInt(KEY_STATE_ID, stateId != null ? stateId : -1)
            .apply();
    }

    public String getToken() {
        return prefs.getString(KEY_TOKEN, null);
    }

    public boolean isLoggedIn() {
        return getToken() != null;
    }

    public String getUserRole() {
        return prefs.getString(KEY_USER_ROLE, Constants.ROLE_PUBLIC);
    }

    public String getUserName() {
        return prefs.getString(KEY_USER_NAME, "User");
    }

    public int getUserId() {
        return prefs.getInt(KEY_USER_ID, 0);
    }

    public int getDistrictId() {
        return prefs.getInt(KEY_DISTRICT_ID, -1);
    }

    public Integer getUserDistrictId() {
        int id = getDistrictId();
        return id != -1 ? id : null;
    }

    public void clear() {
        prefs.edit().clear().apply();
    }

    public void clearSession() {
        clear();
    }

    public String getServerUrl() {
        String saved = prefs.getString("server_url", Constants.DEFAULT_BASE_URL);
        if (saved == null || saved.trim().isEmpty() || saved.contains("trycloudflare.com") || saved.contains("retired-collectible") || saved.contains("helen-hindu") || saved.contains("writings-rhode") || saved.contains("buzz-hiv")) {
            setServerUrl(Constants.DEFAULT_BASE_URL);
            return Constants.DEFAULT_BASE_URL;
        }
        return saved;
    }

    public void setServerUrl(String url) {
        if (url != null && !url.trim().isEmpty()) {
            if (!url.endsWith("/")) {
                url += "/";
            }
            prefs.edit().putString("server_url", url.trim()).apply();
        }
    }
}
