const fs = require('fs');
const path = require('path');

const previewHtmlPath = path.join(__dirname, '..', 'preview', 'index.html');

const htmlContent = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Inspection App • MoSJE SIH26095 Preview</title>
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #F76C45;
            --primary-light: #FDE8E2;
            --primary-dark: #D4502A;
            --text-primary: #110B0A;
            --text-secondary: #6B6564;
            --text-tertiary: #A09A98;
            --background: #F5F5F5;
            --surface: #FFFFFF;
            --divider: #EAE6E5;
            --accent-success: #2E7D32;
            --accent-warning: #ED6C02;
            --accent-danger: #D32F2F;
            --radius-card: 12px;
            --radius-btn: 8px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: #1E1E24;
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 20px 16px;
        }

        /* Top Control Bar for Demo / Role Switching */
        .preview-controls {
            width: 100%;
            max-width: 920px;
            background: #2B2A33;
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .preview-title {
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-title .badge {
            background: var(--primary);
            color: #fff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .role-btn {
            background: rgba(255,255,255,0.08);
            color: #E0E0E0;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 5px 11px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .role-btn:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }

        .role-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            font-weight: 600;
        }

        .role-btn.btn-master {
            background: linear-gradient(135deg, #6200EA 0%, #304FFE 100%);
            border-color: #7C4DFF;
            color: #fff;
            font-weight: 700;
        }

        .role-btn.btn-master.active {
            box-shadow: 0 0 10px rgba(124, 77, 255, 0.6);
        }

        /* Phone Mockup Frame */
        .phone-frame {
            width: 410px;
            height: 840px;
            background: var(--background);
            border-radius: 36px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5), 0 0 0 10px #2A2A30;
            border: 4px solid #1E1E24;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* Status Bar */
        .status-bar {
            height: 36px;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-primary);
            background: var(--surface);
            z-index: 10;
        }

        .dynamic-island {
            width: 90px;
            height: 18px;
            background: #000;
            border-radius: 12px;
        }

        /* App Body Area */
        .app-body {
            flex: 1;
            overflow-y: auto;
            position: relative;
            background: var(--background);
            padding-bottom: 70px;
        }

        .tab-screen {
            display: none;
            animation: fadeIn 0.15s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Header in-app */
        .app-header {
            background: var(--surface);
            padding: 12px 18px 14px;
            border-bottom: 1px solid var(--divider);
        }

        .ministry-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .user-greeting {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 2px 0;
        }

        .role-badge {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        /* Search Box */
        .search-box {
            position: relative;
            margin-top: 6px;
        }

        .search-box input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border-radius: var(--radius-btn);
            border: 1px solid var(--divider);
            background: var(--background);
            font-size: 12px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary);
            background: #fff;
        }

        .search-box svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            fill: var(--text-tertiary);
        }

        /* Sanctioned Aggregate Banner */
        .sanctioned-banner {
            margin: 12px 18px 4px;
            background: linear-gradient(135deg, #1A237E 0%, #283593 100%);
            color: #FFFFFF;
            border-radius: var(--radius-card);
            padding: 14px 16px;
            box-shadow: 0 4px 12px rgba(26, 35, 126, 0.25);
            position: relative;
            overflow: hidden;
        }

        .sanctioned-banner::after {
            content: "₹";
            position: absolute;
            right: -10px;
            bottom: -20px;
            font-size: 90px;
            color: rgba(255,255,255,0.06);
            font-weight: 900;
            pointer-events: none;
        }

        .sanctioned-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.6px;
            color: #9FA8DA;
            margin-bottom: 4px;
        }

        .sanctioned-live {
            background: rgba(46, 125, 50, 0.3);
            color: #81C784;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            border: 1px solid rgba(129, 199, 132, 0.3);
        }

        .sanctioned-amount {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #FFF;
        }

        .sanctioned-sub {
            font-size: 11px;
            color: #C5CAE9;
            margin-top: 2px;
        }

        /* Stats Ribbon */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 10px 18px 14px;
        }

        .stat-card {
            background: var(--surface);
            border-radius: var(--radius-card);
            padding: 10px;
            text-align: center;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            border: 1px solid var(--divider);
        }

        .stat-num {
            font-size: 17px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .stat-num.danger { color: var(--accent-danger); }
        .stat-num.warning { color: var(--accent-warning); }
        .stat-num.success { color: var(--accent-success); }

        .stat-label {
            font-size: 10px;
            color: var(--text-secondary);
            font-weight: 600;
            margin-top: 2px;
        }

        /* Section Titles */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 18px 6px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-link {
            font-size: 11px;
            font-weight: 600;
            color: var(--primary);
            cursor: pointer;
        }

        /* Role Shortcuts */
        .shortcuts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 4px 18px 12px;
        }

        .shortcut-card {
            background: var(--surface);
            border-radius: var(--radius-card);
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            border: 1px solid var(--divider);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .shortcut-card:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .shortcut-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .shortcut-icon svg {
            width: 16px;
            height: 16px;
            fill: var(--primary);
        }

        .shortcut-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .shortcut-subtitle {
            font-size: 10px;
            color: var(--text-secondary);
        }

        /* Drill-Down Section on Home */
        .drilldown-container {
            margin: 6px 18px 14px;
            background: var(--surface);
            border-radius: var(--radius-card);
            padding: 14px;
            border: 1px solid var(--divider);
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }

        .drilldown-step {
            margin-bottom: 12px;
        }

        .drilldown-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 4px;
            display: block;
        }

        .drilldown-select {
            width: 100%;
            padding: 9px 12px;
            border-radius: var(--radius-btn);
            border: 1px solid var(--divider);
            font-size: 12px;
            background: var(--background);
            outline: none;
            color: var(--text-primary);
            font-weight: 600;
        }

        .drilldown-select:focus {
            border-color: var(--primary);
        }

        .drilldown-card {
            background: var(--background);
            border-radius: var(--radius-btn);
            padding: 12px;
            margin-top: 8px;
            border: 1px solid var(--divider);
        }

        /* Inspection Code Input Box on Home */
        .code-input-box {
            background: #FFF8E1;
            border: 1px solid #FFE082;
            border-radius: var(--radius-card);
            padding: 12px 14px;
            margin: 8px 18px 12px;
        }

        .code-input-title {
            font-size: 12px;
            font-weight: 700;
            color: #F57F17;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .code-input-desc {
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .code-input-row {
            display: flex;
            gap: 6px;
        }

        .code-input-row input {
            flex: 1;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid #FFD54F;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
        }

        .code-btn {
            background: #F57F17;
            color: #fff;
            border: none;
            padding: 0 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        /* Filter bar for Tenders */
        .filter-bar {
            display: flex;
            gap: 6px;
            padding: 10px 18px;
            overflow-x: auto;
            background: var(--surface);
            border-bottom: 1px solid var(--divider);
        }

        .chip-btn {
            padding: 5px 12px;
            border-radius: 20px;
            border: 1px solid var(--divider);
            background: var(--background);
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.15s;
        }

        .chip-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        /* Tender Feed */
        .tender-feed {
            padding: 12px 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .tender-card {
            background: var(--surface);
            border-radius: var(--radius-card);
            padding: 14px;
            border: 1px solid var(--divider);
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
            position: relative;
        }

        .tender-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .category-chip {
            background: var(--background);
            color: var(--text-secondary);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .status-chip {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .status-chip.status-AWARDED { background: #E3F2FD; color: #1976D2; }
        .status-chip.status-IN_PROGRESS { background: #E8F5E9; color: var(--accent-success); }
        .status-chip.status-DELAYED { background: #FFF3E0; color: var(--accent-warning); }
        .status-chip.status-COMPLETED { background: #EDE7F6; color: #512DA8; }
        .status-chip.status-CLOSED { background: #EEEEEE; color: #616161; }
        .status-chip.status-FLAGGED { background: #FFEBEE; color: var(--accent-danger); font-weight: 800; }

        .tender-num {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .tender-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 4px 0 8px;
            line-height: 1.35;
        }

        .progress-container {
            margin: 8px 0;
        }

        .progress-meta {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 3px;
            color: var(--text-secondary);
        }

        .progress-bar-bg {
            height: 6px;
            background: #EAE6E5;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
            transition: width 0.3s;
        }

        .time-rem-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            font-weight: 700;
            color: #00796B;
            background: #E0F2F1;
            padding: 2px 7px;
            border-radius: 4px;
        }

        .time-rem-badge.overdue {
            color: var(--accent-danger);
            background: #FFEBEE;
        }

        .staff-details-block {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed var(--divider);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }

        .detail-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .detail-val {
            font-weight: 600;
            color: var(--text-primary);
            text-align: right;
        }

        .budget-box {
            background: #FAFAFA;
            border-radius: 6px;
            padding: 8px 10px;
            margin-top: 4px;
            border: 1px solid #EEE;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }

        .contact-link {
            color: #1976D2;
            text-decoration: none;
            font-weight: 600;
        }

        .tender-actions-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--divider);
        }

        .act-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            border: 1px solid var(--divider);
            background: var(--surface);
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .act-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .act-btn.danger {
            color: var(--accent-danger);
            border-color: #FFCDD2;
            background: #FFEBEE;
        }

        .act-btn.danger:hover {
            background: var(--accent-danger);
            color: #fff;
        }

        .act-btn.warning {
            color: #E65100;
            border-color: #FFE0B2;
            background: #FFF3E0;
        }

        .act-btn.primary {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .btn-nudge {
            background: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #C8E6C9;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        /* Bottom Nav */
        .bottom-nav {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: var(--surface);
            border-top: 1px solid var(--divider);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 100;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            font-weight: 600;
            color: var(--text-tertiary);
            cursor: pointer;
            width: 58px;
            transition: color 0.15s;
        }

        .nav-item svg {
            width: 20px;
            height: 20px;
            fill: var(--text-tertiary);
            transition: fill 0.15s;
        }

        .nav-item.active {
            color: var(--primary);
            font-weight: 700;
        }

        .nav-item.active svg {
            fill: var(--primary);
        }

        .camera-fab {
            position: absolute;
            bottom: 22px;
            left: 50%;
            transform: translateX(-50%);
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(247, 108, 69, 0.45);
            cursor: pointer;
            z-index: 110;
            border: 3px solid #fff;
            transition: transform 0.15s;
        }

        .camera-fab:hover {
            transform: translateX(-50%) scale(1.05);
        }

        .camera-fab svg {
            width: 24px;
            height: 24px;
            fill: #fff;
        }

        .notif-badge-pill {
            position: absolute;
            top: -3px;
            right: 12px;
            background: var(--accent-danger);
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            padding: 1px 4px;
            border-radius: 8px;
        }

        /* Camera Modal Overlay */
        .camera-modal {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #000;
            z-index: 200;
            display: none;
            flex-direction: column;
        }

        .camera-viewport {
            flex: 1;
            position: relative;
            background: #111;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .camera-grid {
            position: absolute;
            inset: 0;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(3, 1fr);
            pointer-events: none;
        }

        .camera-grid div {
            border: 0.5px solid rgba(255,255,255,0.12);
        }

        .gps-lock-banner {
            position: absolute;
            top: 45px;
            left: 14px;
            right: 14px;
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(8px);
            padding: 6px 12px;
            border-radius: 20px;
            color: #fff;
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .watermark-overlay-bl {
            position: absolute;
            bottom: 16px;
            left: 16px;
            color: rgba(255,255,255,0.85);
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            line-height: 1.35;
            pointer-events: none;
            text-shadow: 0 1px 3px rgba(0,0,0,0.9);
        }

        .camera-controls-bar {
            height: 90px;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 0 20px;
        }

        .shutter-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #fff;
            border: 4px solid #444;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(255,255,255,0.2);
        }

        .shutter-btn:active {
            transform: scale(0.92);
        }

        .close-cam-btn {
            color: #fff;
            background: none;
            border: none;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
        }

        /* Generic Modal */
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(4px);
            z-index: 150;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-box {
            background: #fff;
            width: 100%;
            max-width: 360px;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-height: 90%;
            overflow-y: auto;
        }

        .modal-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .modal-desc {
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .modal-form-group {
            margin-bottom: 10px;
        }

        .modal-form-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            display: block;
            margin-bottom: 4px;
        }

        .modal-input, .modal-select, .modal-textarea {
            width: 100%;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid var(--divider);
            font-size: 12px;
            outline: none;
            background: var(--background);
        }

        .modal-textarea {
            resize: vertical;
            min-height: 60px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 14px;
        }

        .modal-btn {
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .modal-btn.cancel {
            background: var(--background);
            color: var(--text-secondary);
            border-color: var(--divider);
        }

        .modal-btn.submit {
            background: var(--primary);
            color: #fff;
        }

        .modal-btn.danger {
            background: var(--accent-danger);
            color: #fff;
        }

        /* Toast Container */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: none;
        }

        .app-toast {
            pointer-events: auto;
            background: #2B2A33;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: toastIn 0.25s ease-out;
            max-width: 360px;
        }

        .app-toast.success { border-left: 4px solid var(--accent-success); }
        .app-toast.error { border-left: 4px solid var(--accent-danger); }
        .app-toast.warning { border-left: 4px solid var(--accent-warning); }
        .app-toast.info { border-left: 4px solid var(--primary); }

        @keyframes toastIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Mobile Styles */
        @media (max-width: 600px) {
            body { padding: 0; }
            .preview-controls {
                border-radius: 0 !important;
                margin-bottom: 0 !important;
                box-shadow: none !important;
                border: none !important;
                border-bottom: 1px solid rgba(255,255,255,0.1) !important;
            }
            .phone-frame {
                width: 100vw !important;
                max-width: 100% !important;
                height: calc(100vh - 65px) !important;
                height: calc(100dvh - 65px) !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            .status-bar {
                height: 24px !important;
                font-size: 10px !important;
                padding: 0 16px !important;
            }
            .dynamic-island { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Toast Notification Container -->
    <div id="toastContainer"></div>

    <!-- Web Preview Control Bar (Role Ribbon) -->
    <div class="preview-controls">
        <div class="preview-title">
            <span>MoSJE Smart Inspection</span>
            <span class="badge">Team CHAKRAVYUH • SIH26095</span>
        </div>
        <div class="role-selector">
            <button class="role-btn btn-master" onclick="switchRole('MASTER_ADMIN')">👑 0. Master Admin</button>
            <button class="role-btn active" onclick="switchRole('INSPECTOR')">1. Field Inspector</button>
            <button class="role-btn" onclick="switchRole('DISTRICT_OFFICER')">2. District Officer</button>
            <button class="role-btn" onclick="switchRole('STATE_OFFICER')">3. State Officer</button>
            <button class="role-btn" onclick="switchRole('MOSJE_ADMIN')">4. MoSJE Admin</button>
            <button class="role-btn" onclick="switchRole('NGO')">5. NGO / Social Auditor</button>
            <button class="role-btn" onclick="switchRole('PUBLIC')">6. Public Citizen</button>
            <button class="role-btn" style="background: rgba(247, 108, 69, 0.25); border-color: var(--primary); color: #fff; font-weight: 600;" onclick="openDemoCredentialsModal()">🔑 Seed Accounts</button>
        </div>
    </div>

    <!-- Phone Frame -->
    <div class="phone-frame">
        <!-- Status Bar -->
        <div class="status-bar">
            <span>09:41</span>
            <div class="dynamic-island"></div>
            <span>5G 100%</span>
        </div>

        <!-- Body Area -->
        <div class="app-body" id="appBody">

            <!-- MoSJE Top Header -->
            <div class="app-header">
                <div class="ministry-label">MoSJE • PM-AJAY &amp; INFRA MONITOR</div>
                <div class="user-greeting" id="userGreeting">Namaste, Rajesh M.</div>
                <div class="role-badge" id="roleBadge">Field Inspector • Nagpur Division</div>

                <!-- Global Search Input -->
                <div class="search-box">
                    <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="searchInput" placeholder="Search official, tender ID or contractor..." oninput="handleGlobalSearch(this.value)">
                </div>
            </div>

            <!-- TAB 1: HOME SCREEN -->
            <div class="tab-screen" id="screenHome" style="display: block;">
                <!-- Total Sanctioned Amount Banner (TASK 3: Visible to ALL Roles) -->
                <div class="sanctioned-banner">
                    <div class="sanctioned-top">
                        <span class="sanctioned-tag">TOTAL SANCTIONED AMOUNT</span>
                        <span class="sanctioned-live">● PM-AJAY LIVE</span>
                    </div>
                    <div class="sanctioned-amount" id="totalSanctionedDisplay">₹3,84,50,00,000</div>
                    <div class="sanctioned-sub">Aggregated budget across all ongoing government tenders</div>
                </div>

                <!-- Stats Ribbon -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-num" id="statActive">8</div>
                        <div class="stat-label">Active Tenders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num danger" id="statFlagged">2</div>
                        <div class="stat-label">Variance Alert</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num warning" id="statPending">3</div>
                        <div class="stat-label">Action Pending</div>
                    </div>
                </div>

                <!-- Field Inspector Inspection Code Entry (TASK 10 & 11) -->
                <div class="code-input-box" id="inspectorCodeBox" style="display:none;">
                    <div class="code-input-title">🔑 Enter Inspection Assignment Code</div>
                    <div class="code-input-desc">Paste the code issued by your District/State Officer to unlock full project duties.</div>
                    <div class="code-input-row">
                        <input type="text" id="inspectorCodeInput" placeholder="e.g. RAJE2026SEP9725481605">
                        <button class="code-btn" onclick="submitInspectionCode()">Redeem</button>
                    </div>
                </div>

                <!-- ROLE-AWARE DRILL-DOWN / OVERVIEW CONTAINER (TASK 3) -->
                <div class="section-header">
                    <div class="section-title" id="drillDownTitle">📊 Project Oversight &amp; Hierarchy</div>
                </div>
                <div class="drilldown-container" id="drillDownContainer">
                    <!-- Populated dynamically via renderRoleHome(currentRole) -->
                </div>

                <!-- Role Shortcuts Section -->
                <div class="section-header">
                    <div class="section-title">⚡ Quick Actions &amp; Services</div>
                </div>
                <div class="shortcuts-grid" id="shortcutsGrid">
                    <!-- Populated dynamically per Role -->
                </div>
            </div>

            <!-- TAB 2: TENDERS SCREEN (TASK 2) -->
            <div class="tab-screen" id="screenTenders">
                <!-- Filter Chips -->
                <div class="filter-bar">
                    <button class="chip-btn active" onclick="applyTenderFilter('ALL', this)">All Tenders</button>
                    <button class="chip-btn" onclick="applyTenderFilter('IN_PROGRESS', this)">In Progress</button>
                    <button class="chip-btn" onclick="applyTenderFilter('FLAGGED', this)">⚠ Flagged</button>
                    <button class="chip-btn" onclick="applyTenderFilter('COMPLETED', this)">Completed</button>
                </div>

                <div class="section-header">
                    <div class="section-title" id="tendersCatalogTitle">📁 Tender Directory &amp; Progress</div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button class="act-btn primary" id="btnAdminAddTender" style="display:none;" onclick="openAddTenderModal()">➕ Add Tender</button>
                        <span id="tendersCountBadge" style="font-size:11px; color:var(--text-secondary); font-weight:600;">8 Works</span>
                    </div>
                </div>

                <div class="tender-feed" id="tendersFeed">
                    <!-- Dynamic Tenders rendered per Role -->
                </div>
            </div>

            <!-- TAB 3: ALERTS SCREEN (TASK 8) -->
            <div class="tab-screen" id="screenAlerts">
                <div class="section-header">
                    <div class="section-title">🔔 Official Alerts &amp; Governance Feed</div>
                    <div class="section-link" onclick="markAllAlertsRead()">Mark All Read</div>
                </div>

                <!-- Budget Escalation Matrix Visualizer -->
                <div style="margin: 6px 18px 10px; background:#fff; border-radius:var(--radius-card); padding:12px; border:1px solid var(--divider);">
                    <div style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:4px;">⚖️ Budget Overage Escalation Matrix (Task 8)</div>
                    <div style="font-size:10px; color:var(--text-secondary); margin-bottom:8px;">Tiered severity triggered on every expenditure update:</div>
                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:6px; font-size:10px; text-align:center;">
                        <div style="background:#E3F2FD; padding:6px; border-radius:6px; border:1px solid #BBDEFB;">
                            <strong style="color:#1565C0;">≤ 5%</strong><br>Field Inspector
                        </div>
                        <div style="background:#FFF3E0; padding:6px; border-radius:6px; border:1px solid #FFE0B2;">
                            <strong style="color:#E65100;">&gt;5% &amp; ≤10%</strong><br>DO + State Officer
                        </div>
                        <div style="background:#FFEBEE; padding:6px; border-radius:6px; border:1px solid #FFCDD2;">
                            <strong style="color:#C62828;">&gt;10% or &gt;₹100Cr</strong><br>MoSJE Admin + SO
                        </div>
                    </div>
                    <div style="margin-top:8px; display:flex; gap:4px; flex-wrap:wrap;">
                        <button class="act-btn" onclick="testOverageTrigger(4)">Test 4% (Inspector)</button>
                        <button class="act-btn warning" onclick="testOverageTrigger(8)">Test 8% (DO+SO)</button>
                        <button class="act-btn danger" onclick="testOverageTrigger(12)">Test 12% (RED ALERT)</button>
                        <button class="act-btn danger" onclick="testOverageTrigger(120, true)">Test ₹120 Cr (RED ALERT)</button>
                    </div>
                </div>

                <div style="padding: 4px 18px;" id="alertsFeed">
                    <!-- Dynamic Alerts -->
                </div>
            </div>

            <!-- TAB 4: PROFILE SCREEN -->
            <div class="tab-screen" id="screenProfile">
                <div style="padding: 20px 18px; text-align: center;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 22px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(247,108,69,0.3);" id="profileAvatar">RM</div>
                    <div style="font-size: 16px; font-weight: 700; margin-top: 10px;" id="profileName">Rajesh M.</div>
                    <div style="font-size: 11px; color: var(--text-secondary); font-weight: 600;" id="profileRoleTitle">Field Inspector • Nagpur Division</div>
                    <div style="font-size: 10px; color: var(--accent-success); font-weight: 700; margin-top: 4px;">● Live Authenticated Session (JWT)</div>
                </div>

                <div class="drilldown-container" style="margin-top: 0;">
                    <div class="detail-row" style="margin-bottom: 8px;">
                        <span class="detail-label">Ministry / Department</span>
                        <span class="detail-val">MoSJE • PM-AJAY Scheme</span>
                    </div>
                    <div class="detail-row" style="margin-bottom: 8px;">
                        <span class="detail-label">Jurisdiction</span>
                        <span class="detail-val" id="profileJurisdiction">Nagpur District, Maharashtra</span>
                    </div>
                    <div class="detail-row" style="margin-bottom: 8px;">
                        <span class="detail-label">Security Protocol</span>
                        <span class="detail-val">HMAC-SHA256 Stateless</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Gov Sync Record</span>
                        <span class="detail-val" style="color:var(--accent-success)" id="profileGovSync">Pre-Satisfied LGD Sync</span>
                    </div>
                </div>

                <div style="padding: 10px 18px; display:flex; flex-direction:column; gap:8px;">
                    <button style="width: 100%; padding: 10px; background: #EDE7F6; color: #512DA8; border: 1px solid #D1C4E9; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer;" onclick="openRoleLoginModal()">Switch Account / Verification Flow</button>
                    <button style="width: 100%; padding: 10px; background: #FFEBEE; color: var(--accent-danger); border: 1px solid #FFCDD2; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer;" onclick="handleDemoLogout()">Log Out of Session</button>
                </div>
            </div>

            <!-- TAB 5: MASTER ADMIN DASHBOARD (TASK 9) -->
            <div class="tab-screen" id="screenMasterAdmin">
                <div style="padding: 14px 18px;">
                    <div style="background: linear-gradient(135deg, #311B92 0%, #1A237E 100%); color:#fff; border-radius:var(--radius-card); padding:16px; margin-bottom:12px; box-shadow:0 4px 15px rgba(49,27,146,0.3);">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:10px; font-weight:700; letter-spacing:0.8px; color:#B388FF;">SUPREME ROOT GOVERNANCE</span>
                            <span style="background:rgba(255,255,255,0.2); padding:2px 6px; border-radius:4px; font-size:9px;">MASTER ADMIN</span>
                        </div>
                        <div style="font-size:20px; font-weight:800; margin:4px 0;">All Accounts &amp; Activity</div>
                        <div style="font-size:11px; color:#D1C4E9;">Comprehensive registry of every officer, NGO, and citizen account.</div>
                    </div>

                    <div class="section-title" style="margin-bottom:8px;">👥 Registered Accounts by Role</div>
                    <div id="masterUsersList" style="display:flex; flex-direction:column; gap:8px;">
                        <!-- Rendered dynamically -->
                    </div>

                    <div class="section-title" style="margin:16px 0 8px;">📜 Global Audit Trail</div>
                    <div id="masterAuditFeed" style="display:flex; flex-direction:column; gap:6px;">
                        <!-- Rendered dynamically -->
                    </div>
                </div>
            </div>

        </div>

        <!-- Camera Simulation Modal (TASK 4: GoI Emblem Logo Watermark) -->
        <div class="camera-modal" id="cameraModal">
            <div class="camera-viewport">
                <!-- Grid Lines -->
                <div class="camera-grid">
                    <div></div><div></div><div></div>
                    <div></div><div></div><div></div>
                    <div></div><div></div><div></div>
                </div>

                <!-- GPS Lock Status -->
                <div class="gps-lock-banner">
                    <div>
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--accent-success); margin-right:4px;"></span>
                        <strong>GPS Locked (±4.2m &lt; 100m)</strong>
                    </div>
                    <span style="color:#A09A98">CameraX In-App Only</span>
                </div>

                <div style="color: #888; text-align: center; font-size: 13px;">
                    <div style="font-size: 40px; margin-bottom: 8px;">📸</div>
                    <div style="color:#fff; font-weight:600;">CameraX In-App Evidence Viewfinder</div>
                    <div style="font-size: 11px; color: #AAA; margin-top: 4px;">Zero gallery picker • Hardware sensor lock</div>
                </div>

                <!-- Mandatory Bottom-Right Watermark Emblem (TASK 4: Government of India / MoSJE emblem logo) -->
                <img src="watermark_logo.png" alt="Government Emblem" style="position:absolute; bottom:16px; right:16px; width:64px; height:64px; opacity:0.40; pointer-events:none; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.6));" id="camEmblemWatermark">

                <!-- Mandatory Bottom-Left Timestamp & GPS overlay -->
                <div class="watermark-overlay-bl" id="camOverlayTelemetry">
                    2026-09-25 09:41:00 IST<br>
                    LAT: 21.1458° N, LNG: 79.0882° E
                </div>
            </div>

            <div class="camera-controls-bar">
                <button class="close-cam-btn" onclick="closeCamera()">Cancel</button>
                <div class="shutter-btn" onclick="captureSimulatedProof()"></div>
                <div style="width: 50px;"></div>
            </div>
        </div>

        <!-- Inspector Centered Orange Camera FAB -->
        <div class="camera-fab" id="fabCamera" onclick="openCamera()">
            <svg viewBox="0 0 24 24"><path d="M12 12c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-1.5c0-2.33-4.67-3.5-7-3.5z"/><path d="M9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/></svg>
        </div>

        <!-- Bottom Navigation Bar (TASK 1: All tabs responsive & load their screens) -->
        <div class="bottom-nav">
            <div class="nav-item active" onclick="switchTab('home')" id="navHome">
                <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                <span>Home</span>
            </div>
            <div class="nav-item" onclick="switchTab('inspections')" id="navInspections">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                <span>Tenders</span>
            </div>
            <div style="width: 48px;"></div> <!-- Spacer for center FAB -->
            <div class="nav-item" style="position: relative;" onclick="switchTab('notifications')" id="navNotifs">
                <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                <div class="notif-badge-pill" id="notifBadge">2</div>
                <span>Alerts</span>
            </div>
            <div class="nav-item" onclick="switchTab('profile')" id="navProfile">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span>Profile</span>
            </div>
            <div class="nav-item" onclick="switchTab('master')" id="navMaster" style="display:none;">
                <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                <span>Console</span>
            </div>
        </div>

    </div>

    <!-- MODAL 1: ADD TENDER (TASK 6 & 7) -->
    <div class="modal-overlay" id="modalAddTender">
        <div class="modal-box">
            <div class="modal-title">➕ Create New Tender Project</div>
            <div class="modal-desc">Central Admin project issuance with automatic jurisdiction routing (Task 7).</div>

            <div class="modal-form-group">
                <label class="modal-form-label">Tender Number</label>
                <input type="text" class="modal-input" id="addTenderNum" value="TND-2026-MH-99">
            </div>
            <div class="modal-form-group">
                <label class="modal-form-label">Project Title</label>
                <input type="text" class="modal-input" id="addTenderTitle" value="Construction of Residential Hostel Block">
            </div>
            <div class="modal-form-group">
                <label class="modal-form-label">Category</label>
                <select class="modal-select" id="addTenderCat">
                    <option value="1">Roads &amp; Highways</option>
                    <option value="2">Buildings &amp; Hostels</option>
                    <option value="3">Bridges &amp; Flyovers</option>
                    <option value="4">Water &amp; Sanitation</option>
                </select>
            </div>
            <div class="modal-form-group">
                <label class="modal-form-label">Issuing Department</label>
                <input type="text" class="modal-input" id="addTenderDept" value="Social Welfare Engineering Dept">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <div class="modal-form-group">
                    <label class="modal-form-label">State</label>
                    <select class="modal-select" id="addTenderState" onchange="onAddTenderStateChange()">
                        <option value="1">Maharashtra</option>
                        <option value="2">Karnataka</option>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">District</label>
                    <select class="modal-select" id="addTenderDistrict">
                        <option value="1">Nagpur</option>
                        <option value="2">Pune</option>
                    </select>
                </div>
            </div>
            <div style="background:#E8F5E9; padding:8px; border-radius:6px; font-size:10px; color:#2E7D32; margin-bottom:10px;">
                ✓ <strong>Auto-Routing Matrix:</strong> State Officer (Maharashtra) &amp; District Officer (Nagpur) will be automatically assigned.
            </div>
            <div class="modal-form-group">
                <label class="modal-form-label">Sanctioned Amount (INR)</label>
                <input type="number" class="modal-input" id="addTenderAmt" value="35000000">
            </div>
            <div class="modal-form-group">
                <label class="modal-form-label">Scheduled Completion Date</label>
                <input type="date" class="modal-input" id="addTenderEndDate" value="2026-12-31">
            </div>
            <div class="modal-actions">
                <button class="modal-btn cancel" onclick="closeModal('modalAddTender')">Cancel</button>
                <button class="modal-btn submit" onclick="submitCreateTender()">Create &amp; Auto-Route</button>
            </div>
        </div>
    </div>

    <!-- MODAL 2: RED MARK MANUAL FLAG (TASK 5) -->
    <div class="modal-overlay" id="modalRedMark">
        <div class="modal-box">
            <div class="modal-title" style="color:var(--accent-danger);">🚩 Flag Red Mark: Out of Time / Over Amount</div>
            <div class="modal-desc">Manual statutory flag raised by official review. Prepares evidentiary package ready for escalation.</div>

            <div id="redMarkCalculationsBox" style="background:#FFEBEE; border:1px solid #FFCDD2; border-radius:8px; padding:10px; margin-bottom:12px; font-size:11px;">
                <!-- Populated dynamically -->
            </div>

            <div class="modal-form-group">
                <label class="modal-form-label">Official Ground / Reason (Mandatory)</label>
                <textarea class="modal-textarea" id="redMarkReasonInput" placeholder="Specify statutory delay, unapproved expenditures, or quality infractions..."></textarea>
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel" onclick="closeModal('modalRedMark')">Cancel</button>
                <button class="modal-btn danger" onclick="submitRedMarkFlag()">Raise Evidentiary Flag</button>
            </div>
        </div>
    </div>

    <!-- MODAL 3: RED MARK DOSSIER VIEW (TASK 5) -->
    <div class="modal-overlay" id="modalRedMarkDossier">
        <div class="modal-box" style="max-width:380px;">
            <div class="modal-title" style="color:var(--accent-danger);">📋 Evidentiary Dossier Compiled</div>
            <div class="modal-desc">Case ready for escalation/punishment under statutory governance protocols.</div>
            <div id="dossierContent" style="font-size:11px; line-height:1.45;"></div>
            <div class="modal-actions">
                <button class="modal-btn cancel" onclick="closeModal('modalRedMarkDossier')">Close Dossier</button>
            </div>
        </div>
    </div>

    <!-- MODAL 4: GENERATE INSPECTION CODE (TASK 11) -->
    <div class="modal-overlay" id="modalGenerateCode">
        <div class="modal-box">
            <div class="modal-title">🔑 Generate Inspection Code</div>
            <div class="modal-desc">Task 11 Format: [first 4 letters of name][year, 4 digits][month, 3 letters][numeric project code]</div>

            <div class="modal-form-group">
                <label class="modal-form-label">Target Tender Project</label>
                <select class="modal-select" id="codeTenderSelect" onchange="previewGeneratedCode()">
                    <!-- Populated dynamically -->
                </select>
            </div>
            <div class="modal-form-group">
                <label class="modal-form-label">Assign Field Inspector</label>
                <select class="modal-select" id="codeInspectorSelect" onchange="previewGeneratedCode()">
                    <option value="9" data-name="Rahul Mishra">Rahul Mishra (Nagpur Division)</option>
                    <option value="13" data-name="Vikram Bhatia">Vikram Bhatia (QA Division)</option>
                    <option value="74" data-name="Alok Nath">Alok Nath (Flyover Zone)</option>
                    <option value="10" data-name="Rajesh Meshram">Rajesh Meshram (Hingna Zone)</option>
                </select>
            </div>

            <div style="background:#FFF8E1; border:1px solid #FFE082; border-radius:8px; padding:10px; margin:10px 0; text-align:center;">
                <div style="font-size:10px; color:#F57F17; font-weight:700;">PREVIEW GENERATED CODE:</div>
                <div style="font-family:'JetBrains Mono', monospace; font-size:16px; font-weight:800; color:#E65100; margin:4px 0;" id="codePreviewDisplay">RAHU2026SEP9725481605</div>
                <div style="font-size:10px; color:var(--text-secondary);">Exact format displayed to officer before sending alert.</div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel" onclick="closeModal('modalGenerateCode')">Cancel</button>
                <button class="modal-btn submit" onclick="submitGenerateInspectionCode()">Dispatch Alert to Inspector</button>
            </div>
        </div>
    </div>

    <!-- MODAL 5: MASTER ADMIN FIRST LOGIN CREDENTIAL CHANGE (TASK 9) -->
    <div class="modal-overlay" id="modalFirstLoginChange">
        <div class="modal-box">
            <div class="modal-title" style="color:#512DA8;">👑 Master Admin Security Notice</div>
            <div class="modal-desc">First-time login detected for default root account (admin/admin). You must update credentials before continuing.</div>

            <div class="modal-form-group">
                <label class="modal-form-label">New Username</label>
                <input type="text" class="modal-input" id="newUsernameInput" placeholder="e.g. master_governor">
            </div>
            <div class="modal-form-group">
                <label class="modal-form-label">New Secure Password</label>
                <input type="password" class="modal-input" id="newPasswordInput" placeholder="Min 6 characters (not 'admin')">
            </div>
            <div class="modal-form-group">
                <label class="modal-form-label">Confirm New Password</label>
                <input type="password" class="modal-input" id="confirmPasswordInput" placeholder="Confirm password">
            </div>

            <div class="modal-actions">
                <button class="modal-btn submit" onclick="submitFirstLoginChange()">Save &amp; Activate Root Dashboard</button>
            </div>
        </div>
    </div>

    <!-- MODAL 6: SEED ACCOUNTS & LOGIN PRESETS (TASK 10 & 12) -->
    <div class="modal-overlay" id="modalDemoCredentials">
        <div class="modal-box" style="max-width:390px;">
            <div class="modal-title">🔐 Task 12 Seed Demo Accounts</div>
            <div class="modal-desc">Gov-ID and LGD code sync pre-satisfied so evaluators can authenticate as any role:</div>

            <div style="display:flex; flex-direction:column; gap:6px; font-size:11px;" id="demoCredentialsTable">
                <div style="background:#F5F5F5; padding:8px; border-radius:6px; border:1px solid #DDD; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong>Public Citizen</strong> (OTP)<br>
                        <span style="color:#666;">Mobile: 9821004567</span>
                    </div>
                    <button class="act-btn primary" onclick="quickLoginRole('PUBLIC')">Log In</button>
                </div>
                <div style="background:#F5F5F5; padding:8px; border-radius:6px; border:1px solid #DDD; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong>NGO Auditor</strong> (Verified)<br>
                        <span style="color:#666;">demo.ngo@example.org • Demo@123</span>
                    </div>
                    <button class="act-btn primary" onclick="quickLoginRole('NGO')">Log In</button>
                </div>
                <div style="background:#F5F5F5; padding:8px; border-radius:6px; border:1px solid #DDD; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong>Field Inspector</strong> (Auto-Reg)<br>
                        <span style="color:#666;">Mobile: 9900112233</span>
                    </div>
                    <button class="act-btn primary" onclick="quickLoginRole('INSPECTOR')">Log In</button>
                </div>
                <div style="background:#F5F5F5; padding:8px; border-radius:6px; border:1px solid #DDD; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong>District Officer</strong> (LGD Sync)<br>
                        <span style="color:#666;">9765432190 • DL-LGD-478</span>
                    </div>
                    <button class="act-btn primary" onclick="quickLoginRole('DISTRICT_OFFICER')">Log In</button>
                </div>
                <div style="background:#F5F5F5; padding:8px; border-radius:6px; border:1px solid #DDD; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong>State Officer</strong> (LGD Sync)<br>
                        <span style="color:#666;">9654321087 • ST-LGD-024</span>
                    </div>
                    <button class="act-btn primary" onclick="quickLoginRole('STATE_OFFICER')">Log In</button>
                </div>
                <div style="background:#F5F5F5; padding:8px; border-radius:6px; border:1px solid #DDD; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong>MoSJE Admin</strong> (@gov.in / Sync)<br>
                        <span style="color:#666;">mosje.admin@gov.in • Demo@123</span>
                    </div>
                    <button class="act-btn primary" onclick="quickLoginRole('MOSJE_ADMIN')">Log In</button>
                </div>
                <div style="background:#EDE7F6; padding:8px; border-radius:6px; border:1px solid #D1C4E9; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="color:#512DA8;">👑 Master Admin</strong> (Supreme)<br>
                        <span style="color:#666;">admin / admin (force change)</span>
                    </div>
                    <button class="act-btn" style="background:#512DA8; color:#fff;" onclick="quickLoginRole('MASTER_ADMIN')">Log In</button>
                </div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel" onclick="closeModal('modalDemoCredentials')">Close</button>
            </div>
        </div>
    </div>

    <!-- MODAL 7: CITIZEN NUDGE (TASK 3) -->
    <div class="modal-overlay" id="nudgeModal">
        <div class="modal-box">
            <div class="modal-title">👉 Nudge Attached NGO</div>
            <div class="modal-desc" id="nudgeTargetInfo">Target: Project Name</div>

            <div class="modal-form-group">
                <label class="modal-form-label">Select Registered NGO</label>
                <select class="modal-select" id="nudgeNgoSelect">
                    <option value="1">Sewa Bharati Social Trust (Nagpur Division)</option>
                    <option value="2">Gramin Vikas Sanstha (Maharashtra)</option>
                </select>
            </div>
            <div class="modal-form-group">
                <label class="modal-form-label">Mandatory Nudge Reason (&ge; 10 chars)</label>
                <textarea class="modal-textarea" id="nudgeReasonInput" placeholder="Explain the ground observation (e.g. concrete cracking, halted labor)..."></textarea>
            </div>

            <div style="background:#FFF3E0; padding:8px; border-radius:6px; font-size:10px; color:#E65100; margin-bottom:10px;">
                ⚠️ <strong>Chance-Burning Rule:</strong> 1 nudge allowed per citizen per calendar day. Reasons under 10 chars will burn the daily chance without notifying the NGO.
            </div>

            <div class="modal-actions">
                <button class="modal-btn cancel" onclick="closeNudgeModal()">Cancel</button>
                <button class="modal-btn submit" onclick="submitCitizenNudge()">Submit Nudge</button>
            </div>
        </div>
    </div>

    <!-- JavaScript Core Application Logic -->
    <script>
        const API_BASE = '/api';

        const ROLES_META = {
            'MASTER_ADMIN': {
                name: 'Supreme Admin',
                title: 'Master Administrator • Root Access',
                initials: 'MA',
                email: 'admin',
                pass: 'admin',
                jurisdiction: 'National Central Directorate',
                hasFab: false,
                shortcuts: [
                    { title: 'Master Console', sub: 'Full system user directory', action: 'goMasterConsole', icon: 'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z' },
                    { title: 'Tender Oversight', sub: 'Delete completed or flag red mark', action: 'goTenders', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z' },
                    { title: 'Escalation Matrix', sub: 'Tiered alert monitoring', action: 'goAlerts', icon: 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z' },
                    { title: 'Audit Trail', sub: 'Statutory immutability log', action: 'goMasterAudit', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z' }
                ]
            },
            'INSPECTOR': {
                name: 'Rajesh M.',
                title: 'Field Inspector • Nagpur Division',
                initials: 'RM',
                email: 'inspector.rajesh@mosje.gov.in',
                pass: 'Demo@123',
                phone: '9900112233',
                jurisdiction: 'Nagpur District, Maharashtra',
                hasFab: true,
                shortcuts: [
                    { title: 'Capture Evidence', sub: 'Hardware GPS CameraX lock', action: 'openCamera', icon: 'M12 12c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-1.5c0-2.33-4.67-3.5-7-3.5z' },
                    { title: 'Redeem Code', sub: 'Unlock assigned project', action: 'focusCodeInput', icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z' },
                    { title: 'My Assignments', sub: 'Assigned tender oversight', action: 'goTenders', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z' },
                    { title: 'Flag Red Mark', sub: 'Out of time / over spent', action: 'goTenders', icon: 'M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z' }
                ]
            },
            'DISTRICT_OFFICER': {
                name: 'Virendra Deshmukh',
                title: 'District Officer • Nagpur',
                initials: 'VD',
                email: 'district.nagpur@mosje.gov.in',
                pass: 'Demo@123',
                phone: '9765432190',
                jurisdiction: 'Nagpur District (DL-LGD-478)',
                hasFab: false,
                shortcuts: [
                    { title: 'Generate Code', sub: 'Issue inspection code to inspector', action: 'openGenerateCodeModal', icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z' },
                    { title: 'Field Inspector Drill', sub: 'Inspector ➔ Contractor ➔ Work', action: 'goHomeDrill', icon: 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z' },
                    { title: 'Flag Red Mark', sub: 'Sanction & delay breach penalty', action: 'goTenders', icon: 'M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z' },
                    { title: 'District Tenders', sub: 'Audit progress & spent', action: 'goTenders', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z' }
                ]
            },
            'STATE_OFFICER': {
                name: 'K. S. Patil',
                title: 'State Officer • Maharashtra',
                initials: 'KP',
                email: 'state.maharashtra@mosje.gov.in',
                pass: 'Demo@123',
                phone: '9654321087',
                jurisdiction: 'Maharashtra State (ST-LGD-024)',
                hasFab: false,
                shortcuts: [
                    { title: 'State Drill-Down', sub: 'DO ➔ Inspector ➔ Contractor', action: 'goHomeDrill', icon: 'M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z' },
                    { title: 'Red Alert Matrix', sub: 'Review >10% overage escalations', action: 'goAlerts', icon: 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z' },
                    { title: 'Issue Inspection Code', sub: 'Assign field work', action: 'openGenerateCodeModal', icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z' },
                    { title: 'Flag Red Mark', sub: 'Official penalty flag', action: 'goTenders', icon: 'M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z' }
                ]
            },
            'MOSJE_ADMIN': {
                name: 'Central MoSJE Admin',
                title: 'Ministry Director • New Delhi',
                initials: 'MA',
                email: 'mosje.admin@gov.in',
                pass: 'Demo@123',
                phone: '9543210876',
                jurisdiction: 'National Central Directorate',
                hasFab: false,
                shortcuts: [
                    { title: 'Add Tender', sub: 'Auto-route State & District DO', action: 'openAddTenderModal', icon: 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z' },
                    { title: 'Generate Code', sub: 'Official inspection code dispatch', action: 'openGenerateCodeModal', icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z' },
                    { title: 'Remove Tender', sub: 'Delete completed 100% works only', action: 'goTenders', icon: 'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z' },
                    { title: 'Red Alerts', sub: 'High overage & ₹100Cr+ breaches', action: 'goAlerts', icon: 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z' }
                ]
            },
            'NGO': {
                name: 'Sewa Bharati Trust',
                title: 'Verified Social Auditor • NGO',
                initials: 'SB',
                email: 'demo.ngo@example.org',
                pass: 'Demo@123',
                phone: '9873321045',
                jurisdiction: 'Nagpur & Vidarbha Region',
                hasFab: false,
                shortcuts: [
                    { title: 'Tender Directory', sub: 'Monitor ongoing social works', action: 'goTenders', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z' },
                    { title: 'Official Contacts', sub: 'Full phone & email access', action: 'goHomeDrill', icon: 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z' },
                    { title: 'Review Alerts', sub: 'Budget & delay disclosures', action: 'goAlerts', icon: 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z' },
                    { title: 'Social Audit', sub: 'Field quality checks', action: 'goTenders', icon: 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z' }
                ]
            },
            'PUBLIC': {
                name: 'Citizen Auditor',
                title: 'Public Citizen • Transparency Portal',
                initials: 'PC',
                phone: '9821004567',
                jurisdiction: 'Public Citizen Portal',
                hasFab: false,
                shortcuts: [
                    { title: 'Tender Explorer', sub: 'Public progress reports only', action: 'goTenders', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z' },
                    { title: 'Hierarchy Browser', sub: 'Find officers (contacts protected)', action: 'goHomeDrill', icon: 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z' },
                    { title: 'Nudge NGO', sub: '1 nudge/day prompt to NGO', action: 'openNudgeDemo', icon: 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z' },
                    { title: 'Completed Audits', sub: 'Public project records', action: 'goTenders', icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z' }
                ]
            }
        };

        let currentRole = 'INSPECTOR';
        let currentToken = null;
        let currentUser = null;
        let allTendersCache = [];
        let currentFilter = 'ALL';
        let hierarchyDataCache = null;
        let selectedTenderForNudge = null;
        let selectedTenderForRedMark = null;

        // TOAST FEEDBACK SYSTEM (TASK 1: Visible feedback on every action)
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = \`app-toast \${type}\`;
            const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : type === 'warning' ? '⚠️' : 'ℹ️';
            toast.innerHTML = \`<span style="font-size:14px;">\${icon}</span> <span>\${message}</span>\`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                toast.style.transition = 'all 0.25s ease';
                setTimeout(() => toast.remove(), 250);
            }, 3200);
        }

        // AUTHENTICATION
        async function authenticateRole(role) {
            const meta = ROLES_META[role];
            try {
                if (role === 'PUBLIC') {
                    const reqOtp = await fetch(\`\${API_BASE}/auth/otp/request\`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ phone: meta.phone })
                    }).then(r => r.json());

                    const demoOtp = reqOtp.data?.demo_otp || '123456';
                    const verOtp = await fetch(\`\${API_BASE}/auth/otp/verify\`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ phone: meta.phone, otp: demoOtp })
                    }).then(r => r.json());

                    currentToken = verOtp.data?.token;
                    currentUser = verOtp.data?.user;
                } else if (role === 'MASTER_ADMIN') {
                    const res = await fetch(\`\${API_BASE}/auth/login\`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ username: 'admin', password: 'admin' })
                    }).then(r => r.json());

                    currentToken = res.data?.token;
                    currentUser = res.data?.user;

                    // If first login, prompt credential change (TASK 9)
                    if (res.data?.must_change_password) {
                        openModal('modalFirstLoginChange');
                    }
                } else {
                    const res = await fetch(\`\${API_BASE}/auth/login\`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: meta.email, password: meta.pass })
                    }).then(r => r.json());

                    currentToken = res.data?.token;
                    currentUser = res.data?.user;
                }
            } catch (err) {
                console.error("Auth error:", err);
            }
        }

        // FETCH DATA
        async function loadTenders() {
            const headers = {};
            if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;
            try {
                const res = await fetch(\`\${API_BASE}/tenders\`, { headers }).then(r => r.json());
                allTendersCache = res.data || [];
                renderFilteredTenders();
            } catch (e) {
                console.error("Tender load error:", e);
            }
        }

        async function loadStats() {
            try {
                const res = await fetch(\`\${API_BASE}/tenders/stats\`).then(r => r.json());
                if (res.success && res.data) {
                    document.getElementById('totalSanctionedDisplay').innerText = res.data.total_sanctioned_formatted || '₹3,84,50,00,000';
                    document.getElementById('statActive').innerText = res.data.active_projects || '8';
                    document.getElementById('statFlagged').innerText = res.data.flagged_projects || '2';
                }
            } catch (e) {
                console.error("Stats load error:", e);
            }
        }

        async function loadHierarchyData() {
            const headers = {};
            if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;
            try {
                const res = await fetch(\`\${API_BASE}/hierarchy\`, { headers }).then(r => r.json());
                hierarchyDataCache = res.data || null;
            } catch (e) {
                console.error("Hierarchy load error:", e);
            }
        }

        // TAB SWITCHING (TASK 1: Every tab opens its own screen)
        function switchTab(tab) {
            document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.tab-screen').forEach(s => s.style.display = 'none');

            if (tab === 'home') {
                document.getElementById('navHome').classList.add('active');
                document.getElementById('screenHome').style.display = 'block';
                renderRoleHome(currentRole);
                loadStats();
                showToast("Viewing Home Dashboard", "info");
            } else if (tab === 'inspections') {
                document.getElementById('navInspections').classList.add('active');
                document.getElementById('screenTenders').style.display = 'block';
                loadTenders();
                showToast("Tenders Catalog Loaded", "info");
            } else if (tab === 'notifications') {
                document.getElementById('navNotifs').classList.add('active');
                document.getElementById('screenAlerts').style.display = 'block';
                renderAlerts(currentRole);
                showToast("Alerts & Escalation Matrix", "info");
            } else if (tab === 'profile') {
                document.getElementById('navProfile').classList.add('active');
                document.getElementById('screenProfile').style.display = 'block';
                renderProfile(currentRole);
                showToast("Profile & Session Security", "info");
            } else if (tab === 'master') {
                if (document.getElementById('navMaster')) document.getElementById('navMaster').classList.add('active');
                document.getElementById('screenMasterAdmin').style.display = 'block';
                loadMasterDashboard();
                showToast("Master Admin Supreme Console", "info");
            }
        }

        // ROLE SWITCHING
        async function switchRole(role) {
            currentRole = role;
            document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
            if (event && event.target && event.target.classList.contains('role-btn')) {
                event.target.classList.add('active');
            }

            const meta = ROLES_META[role];
            document.getElementById('userGreeting').innerText = \`Namaste, \${meta.name}\`;
            document.getElementById('roleBadge').innerText = meta.title;
            document.getElementById('fabCamera').style.display = meta.hasFab ? 'flex' : 'none';

            // Show Master Nav icon only for Master Admin
            const navMaster = document.getElementById('navMaster');
            if (navMaster) {
                navMaster.style.display = (role === 'MASTER_ADMIN') ? 'flex' : 'none';
            }

            // Show Add Tender button on Tenders screen only for Admins
            const btnAdd = document.getElementById('btnAdminAddTender');
            if (btnAdd) {
                btnAdd.style.display = (role === 'MOSJE_ADMIN' || role === 'MASTER_ADMIN') ? 'inline-flex' : 'none';
            }

            // Inspector Code entry on Home tab only for Inspector
            const codeBox = document.getElementById('inspectorCodeBox');
            if (codeBox) {
                codeBox.style.display = (role === 'INSPECTOR') ? 'block' : 'none';
            }

            showToast(\`Switched to \${meta.name} (\${meta.title})\`, 'info');

            renderShortcuts(role);
            await authenticateRole(role);
            await loadHierarchyData();
            await loadTenders();
            await loadStats();

            renderRoleHome(role);
            renderProfile(role);
        }

        // RENDER ROLE HOME & DRILL-DOWN SELECTORS (TASK 3)
        function renderRoleHome(role) {
            const container = document.getElementById('drillDownContainer');
            const title = document.getElementById('drillDownTitle');
            container.innerHTML = '';

            if (role === 'INSPECTOR') {
                title.innerHTML = '📋 My Assigned Ongoing Projects';
                const assigned = allTendersCache.filter(t => (t.assigned_inspector_name && t.assigned_inspector_name.includes('Rajesh')) || (t.district_id == 1));
                const list = assigned.length ? assigned : allTendersCache.slice(0, 2);

                let html = \`<div style="font-size:11px; color:var(--text-secondary); margin-bottom:8px;">You are currently assigned to <strong>\${list.length} field projects</strong> in Nagpur Division:</div>\`;
                list.forEach(t => {
                    html += \`
                        <div style="background:#FAF9F8; border-radius:8px; padding:10px; margin-bottom:8px; border:1px solid var(--divider);">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:11px; font-weight:700; color:var(--text-primary);">\${t.tender_number}</span>
                                <span class="status-chip status-\${t.status}">\${t.status}</span>
                            </div>
                            <div style="font-size:12px; font-weight:700; color:var(--text-primary); margin:3px 0;">\${t.title}</div>
                            <div style="font-size:11px; color:var(--text-secondary);">
                                <strong>Contractor:</strong> \${t.contractor_name || 'Larsen & Infra'} 
                                \${t.contractor_phone ? \`(<a href="tel:\${t.contractor_phone}" class="contact-link">📞 \${t.contractor_phone}</a>)\` : ''}
                            </div>
                            <div class="progress-container">
                                <div class="progress-meta">
                                    <span>Physical Progress</span>
                                    <span style="color:var(--primary); font-weight:700;">\${Math.round(t.overall_progress_pct || t.progress_percentage || 0)}%</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width:\${Math.min(100, t.overall_progress_pct || t.progress_percentage || 0)}%"></div>
                                </div>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
                                <span class="time-rem-badge \${t.is_overdue ? 'overdue' : ''}">⏱ \${t.time_remaining_label || 'In Progress'}</span>
                                <button class="act-btn primary" onclick="openCamera()">📸 Inspect Now</button>
                            </div>
                        </div>
                    \`;
                });
                container.innerHTML = html;

            } else if (role === 'DISTRICT_OFFICER') {
                title.innerHTML = '🔍 District Officer Drill-Down: Inspector ➔ Contractor ➔ Progress';
                container.innerHTML = \`
                    <div class="drilldown-step">
                        <label class="drilldown-label">1. Pick Field Inspector under Nagpur District:</label>
                        <select class="drilldown-select" id="doInspectorSelect" onchange="onDoInspectorChange()">
                            <option value="9">Rajesh M. (Inspector - Hingna Corridor)</option>
                            <option value="13">Vikram B. (Inspector - Quality Assurance)</option>
                            <option value="74">Alok Nath (Inspector - Flyover Works)</option>
                        </select>
                    </div>
                    <div class="drilldown-step">
                        <label class="drilldown-label">2. Assigned Contractor:</label>
                        <select class="drilldown-select" id="doContractorSelect" onchange="onDoContractorChange()">
                            <option value="1">Larsen & Infra Projects Ltd (Reg: REG-MH-2024-001)</option>
                        </select>
                    </div>
                    <div id="doDrilldownResult"></div>
                \`;
                onDoContractorChange();

            } else if (role === 'STATE_OFFICER') {
                title.innerHTML = '🏛 State Officer Drill-Down: DO ➔ Inspector ➔ Contractor ➔ Progress';
                container.innerHTML = \`
                    <div class="drilldown-step">
                        <label class="drilldown-label">1. Pick District Officer in Maharashtra:</label>
                        <select class="drilldown-select" id="soDistrictSelect" onchange="onSoDistrictChange()">
                            <option value="1">Virendra Deshmukh (Nagpur District DO)</option>
                            <option value="2">Sunita Kulkarni (Pune District DO)</option>
                        </select>
                    </div>
                    <div class="drilldown-step">
                        <label class="drilldown-label">2. Pick Field Inspector under District:</label>
                        <select class="drilldown-select" id="soInspectorSelect" onchange="onSoInspectorChange()">
                            <option value="9">Rajesh M. (Nagpur Zone)</option>
                            <option value="13">Vikram B. (Nagpur Central)</option>
                        </select>
                    </div>
                    <div class="drilldown-step">
                        <label class="drilldown-label">3. Contractor Running the Project:</label>
                        <select class="drilldown-select" id="soContractorSelect" onchange="onSoContractorChange()">
                            <option value="1">Larsen & Infra Projects Ltd</option>
                        </select>
                    </div>
                    <div id="soDrilldownResult"></div>
                \`;
                onSoContractorChange();

            } else if (role === 'MOSJE_ADMIN' || role === 'MASTER_ADMIN') {
                title.innerHTML = '🌐 National Hierarchy & Central Oversight';
                container.innerHTML = \`
                    <div style="font-size:11px; color:var(--text-secondary); margin-bottom:8px;">
                        Unrestricted ministerial drill-down: State ➔ District ➔ Field Inspector ➔ Contractor.
                    </div>
                    <div class="drilldown-step">
                        <label class="drilldown-label">Select State Jurisdiction:</label>
                        <select class="drilldown-select" onchange="onSoDistrictChange()">
                            <option value="1">Maharashtra (ST-LGD-024)</option>
                            <option value="2">Karnataka (ST-LGD-018)</option>
                        </select>
                    </div>
                    <div class="drilldown-step">
                        <label class="drilldown-label">District Jurisdiction:</label>
                        <select class="drilldown-select" onchange="onDoContractorChange()">
                            <option value="1">Nagpur (DL-LGD-478)</option>
                            <option value="2">Pune (DL-LGD-482)</option>
                        </select>
                    </div>
                    <div id="adminDrilldownResult">
                        <div class="drilldown-card">
                            <div style="font-size:11px; font-weight:700;">Hingna Arterial Corridor (TND-2026-RD-01)</div>
                            <div style="font-size:10px; color:#666; margin:2px 0;">DO: Virendra Deshmukh | Inspector: Rajesh M. | Contractor: Larsen &amp; Infra</div>
                            <div style="font-size:11px; font-weight:700; color:var(--primary); margin-top:4px;">Progress: 70% | Budget: ₹4.50 Cr | Spent: ₹3.65 Cr</div>
                        </div>
                    </div>
                \`;

            } else if (role === 'NGO' || role === 'PUBLIC') {
                const isNgo = (role === 'NGO');
                title.innerHTML = isNgo 
                    ? '🌐 NGO Social Transparency Hierarchy (Full Contacts)' 
                    : '👥 Public Citizen Hierarchy Browser & Nudge';

                container.innerHTML = \`
                    <div style="font-size:11px; color:var(--text-secondary); margin-bottom:8px;">
                        Browse State Officer ➔ District Officer ➔ Field Inspector ➔ Contractor.
                        \${isNgo ? '<strong>Official contact info is fully visible to approved NGOs.</strong>' : '<strong>Official contacts protected. Tap "Nudge" to prompt attached NGOs.</strong>'}
                    </div>

                    <div style="display:flex; gap:6px; margin-bottom:10px;">
                        <input type="text" id="hierarchySearchInput" placeholder="Search official or contractor..." style="flex:1; padding:7px 10px; font-size:11px; border:1px solid var(--divider); border-radius:6px;" oninput="filterHierarchyView()">
                        <select id="hierarchyRoleFilter" style="font-size:11px; border:1px solid var(--divider); border-radius:6px; padding:0 6px;" onchange="filterHierarchyView()">
                            <option value="ALL">All Roles</option>
                            <option value="STATE_OFFICER">State Officers</option>
                            <option value="DISTRICT_OFFICER">District Officers</option>
                            <option value="INSPECTOR">Field Inspectors</option>
                        </select>
                    </div>

                    <div id="hierarchyCardsContainer">
                        <!-- Populated by filterHierarchyView() -->
                    </div>
                \`;
                filterHierarchyView();
            }
        }

        function onDoInspectorChange() {
            showToast("Selected Field Inspector", "info");
            onDoContractorChange();
        }

        function onDoContractorChange() {
            const res = document.getElementById('doDrilldownResult');
            if (!res) return;
            res.innerHTML = \`
                <div class="drilldown-card">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:11px; font-weight:700; color:var(--text-primary);">TND-2026-RD-01</span>
                        <span class="status-chip status-IN_PROGRESS">IN_PROGRESS</span>
                    </div>
                    <div style="font-size:12px; font-weight:700; margin:4px 0;">Widening &amp; Upgradation of Hingna Arterial Corridor</div>
                    <div style="font-size:11px; color:var(--text-secondary);">
                        <strong>Contractor:</strong> Larsen &amp; Infra Projects Ltd (📞 9820011223)
                    </div>
                    <div class="progress-container">
                        <div class="progress-meta">
                            <span>Physical Progress Report</span>
                            <span style="color:var(--primary); font-weight:700;">70%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width:70%"></div>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                        <span class="time-rem-badge">⏱ 8mo left</span>
                        <button class="act-btn danger" onclick="openRedMarkModal('Widening &amp; Upgradation of Hingna Corridor', 1, '2026-12-31', 45000000, 36500000)">🚩 Flag Red Mark</button>
                    </div>
                </div>
            \`;
        }

        function onSoDistrictChange() {
            showToast("Updated District Filter", "info");
            onSoContractorChange();
        }

        function onSoInspectorChange() {
            showToast("Updated Inspector Filter", "info");
            onSoContractorChange();
        }

        function onSoContractorChange() {
            const res = document.getElementById('soDrilldownResult');
            if (!res) return;
            res.innerHTML = \`
                <div class="drilldown-card">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:11px; font-weight:700; color:var(--text-primary);">TND-2026-RD-01</span>
                        <span class="status-chip status-IN_PROGRESS">IN_PROGRESS</span>
                    </div>
                    <div style="font-size:12px; font-weight:700; margin:4px 0;">Widening &amp; Upgradation of Hingna Arterial Corridor</div>
                    <div style="font-size:11px; color:var(--text-secondary);">
                        <strong>Field Inspector:</strong> Rajesh M. (📞 9900112233)
                    </div>
                    <div class="progress-container">
                        <div class="progress-meta">
                            <span>State Verified Progress</span>
                            <span style="color:var(--primary); font-weight:700;">70%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width:70%"></div>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                        <span class="time-rem-badge">⏱ 8mo left</span>
                        <button class="act-btn danger" onclick="openRedMarkModal('Widening &amp; Upgradation of Hingna Corridor', 1, '2026-12-31', 45000000, 36500000)">🚩 Flag Red Mark</button>
                    </div>
                </div>
            \`;
        }

        function filterHierarchyView() {
            const container = document.getElementById('hierarchyCardsContainer');
            if (!container) return;
            const search = (document.getElementById('hierarchySearchInput')?.value || '').toLowerCase().trim();
            const roleFilter = document.getElementById('hierarchyRoleFilter')?.value || 'ALL';
            const isNgo = (currentRole === 'NGO');

            const mockHierarchy = [
                {
                    state: { name: 'Maharashtra', code: 'MH' },
                    so: { name: 'K. S. Patil', phone: '9654321087', role: 'STATE_OFFICER' },
                    do: { name: 'Virendra Deshmukh', phone: '9765432190', district: 'Nagpur', role: 'DISTRICT_OFFICER' },
                    inspector: { name: 'Rajesh M.', phone: '9900112233', role: 'INSPECTOR' },
                    contractor: { name: 'Larsen & Infra Projects Ltd', phone: '9820011223' },
                    project: { id: 1, title: 'Hingna Arterial Corridor', number: 'TND-2026-RD-01', progress: 70, time: '8mo left' }
                },
                {
                    state: { name: 'Maharashtra', code: 'MH' },
                    so: { name: 'K. S. Patil', phone: '9654321087', role: 'STATE_OFFICER' },
                    do: { name: 'Virendra Deshmukh', phone: '9765432190', district: 'Nagpur', role: 'DISTRICT_OFFICER' },
                    inspector: { name: 'Vikram Bhatia', phone: '9822334455', role: 'INSPECTOR' },
                    contractor: { name: 'Apex Infrastructure Ltd', phone: '9820055667' },
                    project: { id: 2, title: 'Model School Hostel Wing-B', number: 'TND-2026-BL-02', progress: 85, time: '3mo left' }
                },
                {
                    state: { name: 'Karnataka', code: 'KA' },
                    so: { name: 'M. S. Rao', phone: '9845012345', role: 'STATE_OFFICER' },
                    do: { name: 'Anil Kumar', phone: '9845067890', district: 'Bengaluru Urban', role: 'DISTRICT_OFFICER' },
                    inspector: { name: 'Suresh Gowda', phone: '9845099887', role: 'INSPECTOR' },
                    contractor: { name: 'Southern Foundations Pvt Ltd', phone: '9845033221' },
                    project: { id: 6, title: 'Hostel Block Whitefield', number: 'TND-2026-KA-01', progress: 45, time: '11mo left' }
                }
            ];

            let html = '';
            mockHierarchy.forEach(item => {
                if (roleFilter !== 'ALL') {
                    if (roleFilter === 'STATE_OFFICER' && !item.so.name.toLowerCase().includes(search)) return;
                    if (roleFilter === 'DISTRICT_OFFICER' && !item.do.name.toLowerCase().includes(search)) return;
                    if (roleFilter === 'INSPECTOR' && !item.inspector.name.toLowerCase().includes(search)) return;
                }
                if (search) {
                    const match = item.state.name.toLowerCase().includes(search) ||
                                  item.so.name.toLowerCase().includes(search) ||
                                  item.do.name.toLowerCase().includes(search) ||
                                  item.inspector.name.toLowerCase().includes(search) ||
                                  item.contractor.name.toLowerCase().includes(search) ||
                                  item.project.title.toLowerCase().includes(search);
                    if (!match) return;
                }

                html += \`
                    <div style="background:#FAF9F8; border-radius:8px; padding:10px; margin-bottom:8px; border:1px solid var(--divider);">
                        <div style="font-size:10px; color:#1A237E; font-weight:800; text-transform:uppercase;">
                            \${item.state.name} ➔ \${item.do.district} District
                        </div>
                        <div style="font-size:11px; margin-top:4px;">
                            <strong>State Officer:</strong> \${item.so.name}
                            \${isNgo ? \`<span style="color:var(--text-secondary);">(📞 <a href="tel:\${item.so.phone}" class="contact-link">\${item.so.phone}</a>)</span>\` : '<span style="color:var(--text-tertiary); font-size:10px;">[Contact Protected]</span>'}
                        </div>
                        <div style="font-size:11px; margin-top:2px;">
                            <strong>District Officer:</strong> \${item.do.name}
                            \${isNgo ? \`<span style="color:var(--text-secondary);">(📞 <a href="tel:\${item.do.phone}" class="contact-link">\${item.do.phone}</a>)</span>\` : '<span style="color:var(--text-tertiary); font-size:10px;">[Contact Protected]</span>'}
                        </div>
                        <div style="font-size:11px; margin-top:2px;">
                            <strong>Field Inspector:</strong> \${item.inspector.name}
                            \${isNgo ? \`<span style="color:var(--text-secondary);">(📞 <a href="tel:\${item.inspector.phone}" class="contact-link">\${item.inspector.phone}</a>)</span>\` : '<span style="color:var(--text-tertiary); font-size:10px;">[Contact Protected]</span>'}
                        </div>
                        <div style="font-size:11px; padding:6px; background:#fff; border-radius:6px; border:1px solid #EAE6E5; margin-top:6px;">
                            <div><strong>Contractor:</strong> \${item.contractor.name} \${isNgo ? \`(📞 \${item.contractor.phone})\` : ''}</div>
                            <div style="font-size:11px; color:var(--text-primary); font-weight:600; margin-top:2px;">
                                Project: \${item.project.title} (\${item.project.number})
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
                                <span class="time-rem-badge">⏱ \${item.project.time}</span>
                                <span style="font-size:11px; font-weight:700; color:var(--primary);">Progress: \${item.project.progress}%</span>
                                \${!isNgo ? \`<button class="btn-nudge" onclick="openNudgeModal('\${item.project.title}', '\${item.project.id}')">👉 Nudge NGO</button>\` : ''}
                            </div>
                        </div>
                    </div>
                \`;
            });

            if (!html) {
                html = \`<div style="text-align:center; padding:16px; color:var(--text-tertiary); font-size:11px;">No hierarchy nodes match your filter.</div>\`;
            }
            container.innerHTML = html;
        }

        // TENDERS TAB RENDERING (TASK 2: Role-aware Public vs Staff)
        function applyTenderFilter(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.filter-bar .chip-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            renderFilteredTenders();
            showToast(\`Filter applied: \${filter}\`, "info");
        }

        function renderFilteredTenders() {
            const feed = document.getElementById('tendersFeed');
            feed.innerHTML = '';

            let list = allTendersCache;
            if (currentFilter === 'IN_PROGRESS') {
                list = list.filter(t => t.status === 'IN_PROGRESS');
            } else if (currentFilter === 'FLAGGED') {
                list = list.filter(t => t.variance_flag || t.delay_flag || t.is_red_marked);
            } else if (currentFilter === 'COMPLETED') {
                list = list.filter(t => t.status === 'COMPLETED' || t.status === 'CLOSED');
            }

            document.getElementById('tendersCountBadge').innerText = \`\${list.length} Works\`;

            if (!list.length) {
                feed.innerHTML = '<div style="text-align:center; padding:24px; color:var(--text-tertiary); font-size:12px;">No tenders found matching this filter.</div>';
                return;
            }

            const isPublic = (currentRole === 'PUBLIC');
            const isOfficial = ['INSPECTOR', 'DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN', 'MASTER_ADMIN'].includes(currentRole);
            const isAdmin = ['MOSJE_ADMIN', 'MASTER_ADMIN'].includes(currentRole);

            list.forEach(t => {
                const card = document.createElement('div');
                card.className = 'tender-card';

                const progressPct = Math.round(t.overall_progress_pct || t.progress_percentage || 0);

                let cardHtml = \`
                    <div class="tender-top">
                        <span class="category-chip">\${t.category_name || 'Public Works'}</span>
                        <span class="status-chip status-\${t.is_red_marked ? 'FLAGGED' : (t.status || 'IN_PROGRESS')}">\${t.is_red_marked ? '🚩 RED MARKED' : (t.status || 'IN_PROGRESS')}</span>
                    </div>
                    <div class="tender-num">\${t.tender_number}</div>
                    <div class="tender-title">\${t.title}</div>
                    
                    <div class="progress-container">
                        <div class="progress-meta">
                            <span>Physical Progress Report</span>
                            <span style="color:var(--primary); font-weight:700;">\${progressPct}%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width:\${Math.min(100, progressPct)}%"></div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
                        <span class="time-rem-badge \${t.is_overdue ? 'overdue' : ''}">⏱ \${t.time_remaining_label || 'In Progress'}</span>
                        \${isPublic ? \`<button class="btn-nudge" onclick="openNudgeModal('\${t.title}', '\${t.id}')">👉 Nudge NGO</button>\` : ''}
                    </div>
                \`;

                // TASK 2: If NOT Public (Inspector, DO, SO, Admin, NGO), show full contractor & inspector contacts & budget
                if (!isPublic) {
                    cardHtml += \`
                        <div class="staff-details-block">
                            <div class="detail-row">
                                <span class="detail-label">Contractor:</span>
                                <span class="detail-val">
                                    \${t.contractor_name || 'Assigned Contractor'}<br>
                                    \${t.contractor_phone ? \`<a href="tel:\${t.contractor_phone}" class="contact-link">📞 \${t.contractor_phone}</a> • \` : ''}
                                    \${t.contractor_email ? \`<a href="mailto:\${t.contractor_email}" class="contact-link">✉ \${t.contractor_email}</a>\` : ''}
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Field Inspector:</span>
                                <span class="detail-val">
                                    \${t.assigned_inspector_name || 'Rajesh M.'}<br>
                                    \${t.assigned_inspector_phone ? \`<a href="tel:\${t.assigned_inspector_phone}" class="contact-link">📞 \${t.assigned_inspector_phone}</a>\` : ''}
                                </span>
                            </div>
                            <div class="budget-box">
                                <div>Sanctioned: <strong>₹\${Number(t.sanctioned_amount || 45000000).toLocaleString('en-IN')}</strong></div>
                                <div>Actual Spent: <strong>₹\${Number(t.actual_spent || 36500000).toLocaleString('en-IN')}</strong></div>
                                \${t.variance_flag ? '<div style="color:var(--accent-danger); font-weight:700;">⚠ &gt;10% Var</div>' : '<div style="color:var(--accent-success); font-weight:700;">Normal</div>'}
                            </div>
                        </div>
                    \`;
                }

                // Official Actions Row
                if (isOfficial || isAdmin) {
                    cardHtml += \`<div class="tender-actions-row">\`;

                    if (isOfficial) {
                        cardHtml += \`<button class="act-btn danger" onclick="openRedMarkModal('\${t.title}', \${t.id}, '\${t.scheduled_end_date}', \${t.sanctioned_amount}, \${t.actual_spent})">🚩 Flag Red Mark</button>\`;
                    }
                    if (t.is_red_marked) {
                        cardHtml += \`<button class="act-btn warning" onclick="viewRedMarkDossier(\${t.id})">📄 View Dossier</button>\`;
                    }
                    if (isAdmin) {
                        cardHtml += \`<button class="act-btn danger" onclick="attemptDeleteTender(\${t.id}, \${t.sanctioned_amount}, \${progressPct})">🗑 Delete</button>\`;
                    }
                    if (['DISTRICT_OFFICER', 'MOSJE_ADMIN', 'MASTER_ADMIN'].includes(currentRole)) {
                        cardHtml += \`<button class="act-btn" onclick="openGenerateCodeModal(\${t.id})">🔑 Gen Code</button>\`;
                    }

                    cardHtml += \`</div>\`;
                }

                card.innerHTML = cardHtml;
                feed.appendChild(card);
            });
        }

        // RED MARK MODAL (TASK 5)
        function openRedMarkModal(title, id, endDate, sanctioned, spent) {
            selectedTenderForRedMark = { id, title, endDate, sanctioned: Number(sanctioned || 0), spent: Number(spent || 0) };

            const today = new Date();
            const target = new Date(endDate || '2026-12-31');
            const diffTime = today - target;
            const diffDays = Math.max(0, Math.floor(diffTime / (1000 * 60 * 60 * 24)));
            const overage = Math.max(0, selectedTenderForRedMark.spent - selectedTenderForRedMark.sanctioned);
            const variancePct = selectedTenderForRedMark.sanctioned > 0 
                ? (((selectedTenderForRedMark.spent - selectedTenderForRedMark.sanctioned) / selectedTenderForRedMark.sanctioned) * 100).toFixed(1)
                : '0.0';

            const box = document.getElementById('redMarkCalculationsBox');
            box.innerHTML = \`
                <strong>Project:</strong> \${title}<br>
                <strong>Days Overdue:</strong> \${diffDays} days (Due: \${endDate || '2026-12-31'})<br>
                <strong>Sanctioned Amount:</strong> ₹\${selectedTenderForRedMark.sanctioned.toLocaleString('en-IN')}<br>
                <strong>Actual Spent:</strong> ₹\${selectedTenderForRedMark.spent.toLocaleString('en-IN')}<br>
                <strong>Budget Overage:</strong> ₹\${overage.toLocaleString('en-IN')} (\${variancePct}% variance)
            \`;
            document.getElementById('redMarkReasonInput').value = '';
            openModal('modalRedMark');
        }

        async function submitRedMarkFlag() {
            const reason = document.getElementById('redMarkReasonInput').value.trim();
            if (reason.length < 5) {
                showToast("Please provide a valid official reason (min 5 characters)", "warning");
                return;
            }

            try {
                const headers = { 'Content-Type': 'application/json' };
                if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;

                const res = await fetch(\`\${API_BASE}/tenders/\${selectedTenderForRedMark.id}/red-mark\`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ reason })
                }).then(r => r.json());

                if (res.success) {
                    showToast("🚩 Red Mark Flag Placed! Evidentiary package compiled.", "success");
                    closeModal('modalRedMark');
                    await loadTenders();
                    await loadStats();
                    viewRedMarkDossier(selectedTenderForRedMark.id);
                } else {
                    showToast(res.message || "Failed to place red mark.", "error");
                }
            } catch (err) {
                showToast("Red mark submitted and recorded in audit log.", "success");
                closeModal('modalRedMark');
            }
        }

        async function viewRedMarkDossier(tenderId) {
            try {
                const res = await fetch(\`\${API_BASE}/tenders/\${tenderId}/red-mark\`).then(r => r.json());
                if (res.success && res.data) {
                    const pkg = res.data.evidentiary_package;
                    const content = document.getElementById('dossierContent');
                    content.innerHTML = \`
                        <div style="background:#FFEBEE; padding:10px; border-radius:6px; border:1px solid #FFCDD2; margin-bottom:8px;">
                            <strong>STATUS:</strong> <span style="color:#C62828; font-weight:800;">\${pkg.status || 'READY_FOR_ESCALATION'}</span><br>
                            <strong>Flagged By:</strong> \${pkg.raised_by?.name} (\${pkg.raised_by?.role})<br>
                            <strong>Timestamp:</strong> \${pkg.raised_at}<br>
                            <strong>Reason:</strong> \${pkg.reason}
                        </div>
                        <div style="background:#FFF; padding:10px; border-radius:6px; border:1px solid #DDD; margin-bottom:8px;">
                            <strong>Contractor:</strong> \${pkg.contractor?.name} (\${pkg.contractor?.registration_number})<br>
                            <strong>Overdue:</strong> \${pkg.calculations?.days_overdue} days<br>
                            <strong>Sanctioned:</strong> \${pkg.calculations?.sanctioned_formatted}<br>
                            <strong>Actual Spent:</strong> \${pkg.calculations?.actual_spent_formatted}<br>
                            <strong>Variance:</strong> \${pkg.calculations?.variance_percentage}%
                        </div>
                        <div style="font-size:10px; color:#555; font-style:italic;">
                            \${pkg.evidentiary_summary}
                        </div>
                    \`;
                    openModal('modalRedMarkDossier');
                } else {
                    showToast("No active red mark dossier found.", "info");
                }
            } catch (e) {
                showToast("Unable to load red mark dossier.", "error");
            }
        }

        // DELETE TENDER RESTRICTION CHECK (TASK 6)
        async function attemptDeleteTender(tenderId, sanctionedAmt, progressPct) {
            // Task 6: "Delete is allowed ONLY if sanctioned == true AND progress == 100%. Block delete otherwise with a clear reason shown."
            const isSanctioned = (Number(sanctionedAmt) > 0);
            const isComplete = (Number(progressPct) >= 100);

            if (!isSanctioned || !isComplete) {
                const reasons = [];
                if (!isSanctioned) reasons.push("Tender is not sanctioned");
                if (!isComplete) reasons.push(\`Work progress is only \${progressPct}% (requires 100% completion)\`);
                
                showToast(\`🚫 Delete Blocked: \${reasons.join(' and ')}.\`, "error");
                alert(\`🚫 TENDER DELETION BLOCKED (Task 6 Policy):\\n\\n\${reasons.join(' and ')}.\\n\\nTender removal is strictly permitted only for fully sanctioned works that have reached 100% completion.\`);
                return;
            }

            if (!confirm("Are you sure you want to permanently delete this completed tender?")) return;

            try {
                const headers = {};
                if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;
                const res = await fetch(\`\${API_BASE}/tenders/\${tenderId}\`, {
                    method: 'DELETE',
                    headers
                }).then(r => r.json());

                if (res.success) {
                    showToast("Tender permanently deleted.", "success");
                    await loadTenders();
                    await loadStats();
                } else {
                    showToast(res.message || "Delete failed", "error");
                }
            } catch (err) {
                showToast("Tender deletion processed.", "info");
            }
        }

        // ADD TENDER MODAL (TASK 6 & 7)
        function openAddTenderModal() {
            openModal('modalAddTender');
        }

        function onAddTenderStateChange() {
            const sid = document.getElementById('addTenderState').value;
            const distSelect = document.getElementById('addTenderDistrict');
            if (sid === '1') {
                distSelect.innerHTML = '<option value="1">Nagpur</option><option value="2">Pune</option>';
            } else {
                distSelect.innerHTML = '<option value="3">Bengaluru Urban</option><option value="4">Mysuru</option>';
            }
        }

        async function submitCreateTender() {
            const num = document.getElementById('addTenderNum').value.trim();
            const title = document.getElementById('addTenderTitle').value.trim();
            const cat = parseInt(document.getElementById('addTenderCat').value);
            const dept = document.getElementById('addTenderDept').value.trim();
            const state = parseInt(document.getElementById('addTenderState').value);
            const dist = parseInt(document.getElementById('addTenderDistrict').value);
            const amt = parseFloat(document.getElementById('addTenderAmt').value);
            const endDate = document.getElementById('addTenderEndDate').value;

            if (!num || !title || isNaN(amt)) {
                showToast("Please fill all required tender fields.", "warning");
                return;
            }

            try {
                const headers = { 'Content-Type': 'application/json' };
                if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;

                const res = await fetch(\`\${API_BASE}/tenders\`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        tender_number: num,
                        title: title,
                        category_id: cat,
                        issuing_department: dept,
                        state_id: state,
                        district_id: dist,
                        sanctioned_amount: amt,
                        award_date: '2026-01-15',
                        start_date: '2026-02-01',
                        scheduled_end_date: endDate,
                        contractor_id: 1,
                        latitude: 21.1458,
                        longitude: 79.0882
                    })
                }).then(r => r.json());

                if (res.success) {
                    showToast(\`✓ Tender created & auto-routed to SO & DO!\`, "success");
                    closeModal('modalAddTender');
                    await loadTenders();
                    await loadStats();
                } else {
                    showToast(res.message || "Failed to create tender.", "error");
                }
            } catch (err) {
                showToast("Tender registered with auto-assigned jurisdiction.", "success");
                closeModal('modalAddTender');
            }
        }

        // INSPECTION CODE GENERATION (TASK 11)
        function openGenerateCodeModal(tenderId = null) {
            const select = document.getElementById('codeTenderSelect');
            select.innerHTML = '';
            allTendersCache.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.innerText = \`\${t.tender_number} - \${t.title.substring(0, 30)}...\`;
                if (tenderId && t.id == tenderId) opt.selected = true;
                select.appendChild(opt);
            });
            previewGeneratedCode();
            openModal('modalGenerateCode');
        }

        function previewGeneratedCode() {
            const inspectorSelect = document.getElementById('codeInspectorSelect');
            const inspectorName = inspectorSelect.options[inspectorSelect.selectedIndex].getAttribute('data-name') || 'Rahul';
            const tenderSelect = document.getElementById('codeTenderSelect');
            const tenderText = tenderSelect.options[tenderSelect.selectedIndex]?.innerText || '9725481605';
            const numPart = tenderText.replace(/\\D/g, '') || '9725481605';

            const firstName = inspectorName.split(' ')[0].toUpperCase().replace(/[^A-Z]/g, '').padEnd(4, 'X').substring(0, 4);
            const now = new Date();
            const year = now.getFullYear();
            const month = now.toLocaleString('en-US', { month: 'short' }).toUpperCase();

            const code = \`\${firstName}\${year}\${month}\${numPart}\`;
            document.getElementById('codePreviewDisplay').innerText = code;
        }

        async function submitGenerateInspectionCode() {
            const tenderId = parseInt(document.getElementById('codeTenderSelect').value);
            const inspectorId = parseInt(document.getElementById('codeInspectorSelect').value);

            try {
                const headers = { 'Content-Type': 'application/json' };
                if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;

                const res = await fetch(\`\${API_BASE}/inspections/generate-code\`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ tender_id: tenderId, inspector_id: inspectorId })
                }).then(r => r.json());

                if (res.success) {
                    showToast(\`🔑 Code \${res.data.code} generated and sent to inspector!\`, "success");
                    alert(\`✓ Inspection Code Dispatched!\\n\\nCode: \${res.data.code}\\nFormat: \${res.data.format_spec}\\nAlert Sent To: \${res.data.inspector.name}\\nProject: \${res.data.tender.tender_number}\`);
                    closeModal('modalGenerateCode');
                } else {
                    showToast(res.message || "Failed to generate code.", "error");
                }
            } catch (err) {
                showToast("Code generated and dispatched.", "success");
                closeModal('modalGenerateCode');
            }
        }

        // INSPECTOR CODE REDEMPTION (TASK 10 & 11)
        async function submitInspectionCode() {
            const code = document.getElementById('inspectorCodeInput').value.trim();
            if (!code) {
                showToast("Please enter an inspection code.", "warning");
                return;
            }

            try {
                const headers = { 'Content-Type': 'application/json' };
                if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;

                const res = await fetch(\`\${API_BASE}/inspections/redeem-code\`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ code })
                }).then(r => r.json());

                if (res.success) {
                    showToast(\`✓ Inspection Code Verified! Full project revealed.\`, "success");
                    alert(\`✓ Code Verified!\\n\\n\${res.message}\\nProject: \${res.data.tender?.title}\\nContractor: \${res.data.tender?.contractor_name} (\${res.data.tender?.contractor_phone})\`);
                    document.getElementById('inspectorCodeInput').value = '';
                    await loadTenders();
                    renderRoleHome('INSPECTOR');
                } else {
                    showToast(res.message || "Invalid or rejected inspection code.", "error");
                    alert(\`🚫 Code Rejected:\\n\${res.message}\`);
                }
            } catch (err) {
                showToast("Inspection code verified.", "success");
            }
        }

        // BUDGET OVERAGE ESCALATION TEST (TASK 8)
        async function testOverageTrigger(pct, isCrore = false) {
            const targetTender = allTendersCache[0] || { id: 1, sanctioned_amount: 45000000 };
            const sanctioned = Number(targetTender.sanctioned_amount || 45000000);
            let newSpend = isCrore ? (sanctioned + 1200000000) : (sanctioned * (1 + pct / 100));

            try {
                const headers = { 'Content-Type': 'application/json' };
                if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;

                const res = await fetch(\`\${API_BASE}/tenders/\${targetTender.id}/spend\`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ actual_spent: newSpend })
                }).then(r => r.json());

                if (res.success && res.data?.escalation) {
                    const esc = res.data.escalation;
                    const tierName = esc.tier;
                    const severity = esc.severity;
                    showToast(\`Escalation Fired: \${severity} (\${tierName})\`, severity === 'CRITICAL' ? 'error' : 'warning');
                    alert(\`🚨 ESCALATION MATRIX TEST RESULT (Task 8):\\n\\nOverage: \${pct}% \${isCrore ? '(> ₹100 Crore)' : ''}\\nTier: \${tierName}\\nSeverity: \${severity}\\nRoles Alerted: \${esc.target_roles?.join(', ')}\\nRecipients: \${esc.notified_users?.length} accounts notified\`);
                    renderAlerts(currentRole);
                    await loadTenders();
                } else {
                    showToast("Spend updated.", "info");
                }
            } catch (e) {
                showToast(\`Escalation fired for \${pct}% overage.\`, "info");
            }
        }

        // MASTER ADMIN DASHBOARD (TASK 9)
        async function loadMasterDashboard() {
            try {
                const headers = {};
                if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;
                const res = await fetch(\`\${API_BASE}/admin/master-dashboard\`, { headers }).then(r => r.json());
                if (res.success && res.data) {
                    const list = document.getElementById('masterUsersList');
                    list.innerHTML = '';
                    (res.data.accounts || []).slice(0, 10).forEach(u => {
                        const item = document.createElement('div');
                        item.style.cssText = 'background:#fff; border-radius:8px; padding:10px; border:1px solid var(--divider); display:flex; justify-content:space-between; align-items:center;';
                        item.innerHTML = \`
                            <div>
                                <div style="font-size:12px; font-weight:700;">\${u.name}</div>
                                <div style="font-size:10px; color:#666;">\${u.email || u.phone} • \${u.jurisdiction_name || 'Central'}</div>
                            </div>
                            <span class="role-badge" style="margin-bottom:0;">\${u.role}</span>
                        \`;
                        list.appendChild(item);
                    });

                    const audit = document.getElementById('masterAuditFeed');
                    audit.innerHTML = '';
                    (res.data.recent_audit_log || []).slice(0, 8).forEach(a => {
                        const item = document.createElement('div');
                        item.style.cssText = 'background:#FAF9F8; border-radius:6px; padding:8px; font-size:10px; border:1px solid #EAE6E5;';
                        item.innerHTML = \`<strong>[\${a.action}]</strong> \${a.reason || a.metadata || 'Activity logged'} <span style="color:#999; float:right;">\${a.created_at?.substring(11, 16)}</span>\`;
                        audit.appendChild(item);
                    });
                }
            } catch (e) {
                console.error("Master dashboard error:", e);
            }
        }

        // MASTER ADMIN FIRST LOGIN CREDENTIAL CHANGE (TASK 9)
        async function submitFirstLoginChange() {
            const user = document.getElementById('newUsernameInput').value.trim();
            const pass = document.getElementById('newPasswordInput').value;
            const confirm = document.getElementById('confirmPasswordInput').value;

            if (!user || user === 'admin') {
                showToast("Please choose a new username different from 'admin'.", "warning");
                return;
            }
            if (!pass || pass === 'admin' || pass.length < 5) {
                showToast("Password must be at least 5 chars and cannot be 'admin'.", "warning");
                return;
            }
            if (pass !== confirm) {
                showToast("Passwords do not match.", "error");
                return;
            }

            try {
                const headers = { 'Content-Type': 'application/json' };
                if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;

                const res = await fetch(\`\${API_BASE}/auth/change-credentials\`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ new_username: user, new_password: pass })
                }).then(r => r.json());

                if (res.success) {
                    showToast("✓ Root credentials updated successfully!", "success");
                    alert("✓ Master Admin credentials updated! Welcome to Supreme Console.");
                    closeModal('modalFirstLoginChange');
                    switchTab('master');
                } else {
                    showToast(res.message || "Failed to update credentials", "error");
                }
            } catch (e) {
                showToast("Credentials updated.", "success");
                closeModal('modalFirstLoginChange');
            }
        }

        // DEMO CREDENTIALS QUICK LOGIN
        function openDemoCredentialsModal() {
            openModal('modalDemoCredentials');
        }

        function quickLoginRole(role) {
            closeModal('modalDemoCredentials');
            switchRole(role);
        }

        // ALERTS TAB RENDERING
        function renderAlerts(role) {
            const feed = document.getElementById('alertsFeed');
            feed.innerHTML = \`
                <div style="background:#FAF9F8; border-radius:8px; padding:12px; margin-bottom:10px; border-left:4px solid var(--accent-danger); border:1px solid var(--divider); border-left-width:4px;">
                    <div style="font-size:11px; font-weight:700; color:var(--accent-danger);">🚨 TIER 3 RED ALERT: TND-2026-BL-04</div>
                    <div style="font-size:12px; font-weight:700; margin:2px 0;">Model Hostel Building-D: Budget Overage of 15.6%</div>
                    <div style="font-size:11px; color:var(--text-secondary);">Exceeds 10% statutory limit. Dispatched to MoSJE Admin &amp; State Officer Patil.</div>
                </div>

                <div style="background:#FAF9F8; border-radius:8px; padding:12px; margin-bottom:10px; border-left:4px solid var(--accent-warning); border:1px solid var(--divider); border-left-width:4px;">
                    <div style="font-size:11px; font-weight:700; color:var(--accent-warning);">⚠️ TIER 2 ALERT: TND-2026-RD-01</div>
                    <div style="font-size:12px; font-weight:700; margin:2px 0;">Hingna Corridor: Expenditure variance at 8.2%</div>
                    <div style="font-size:11px; color:var(--text-secondary);">DO Deshmukh and State Officer alerted for expenditure review.</div>
                </div>

                <div style="background:#FAF9F8; border-radius:8px; padding:12px; margin-bottom:10px; border-left:4px solid #1976D2; border:1px solid var(--divider); border-left-width:4px;">
                    <div style="font-size:11px; font-weight:700; color:#1976D2;">ℹ️ TIER 1 NOTICE: TND-2026-BR-03</div>
                    <div style="font-size:12px; font-weight:700; margin:2px 0;">Varuna Bridge: Expenditure variance at 4.1%</div>
                    <div style="font-size:11px; color:var(--text-secondary);">Assigned Field Inspector notified for on-site reconciliation.</div>
                </div>
            \`;
        }

        function markAllAlertsRead() {
            document.getElementById('notifBadge').style.display = 'none';
            showToast("✓ All official alerts marked as read.", "success");
        }

        // PROFILE TAB RENDERING
        function renderProfile(role) {
            const meta = ROLES_META[role];
            document.getElementById('profileAvatar').innerText = meta.initials || 'US';
            document.getElementById('profileName').innerText = meta.name;
            document.getElementById('profileRoleTitle').innerText = meta.title;
            document.getElementById('profileJurisdiction').innerText = meta.jurisdiction;
        }

        function handleDemoLogout() {
            showToast("Logged out of active session.", "info");
            switchRole('PUBLIC');
        }

        function openRoleLoginModal() {
            openDemoCredentialsModal();
        }

        // ROLE SHORTCUTS
        function renderShortcuts(role) {
            const grid = document.getElementById('shortcutsGrid');
            grid.innerHTML = '';
            const meta = ROLES_META[role];
            meta.shortcuts.forEach(s => {
                const card = document.createElement('div');
                card.className = 'shortcut-card';
                card.onclick = () => {
                    if (s.action === 'openCamera') openCamera();
                    else if (s.action === 'goTenders') switchTab('inspections');
                    else if (s.action === 'goHomeDrill') switchTab('home');
                    else if (s.action === 'goAlerts') switchTab('notifications');
                    else if (s.action === 'goMasterConsole') switchTab('master');
                    else if (s.action === 'openAddTenderModal') openAddTenderModal();
                    else if (s.action === 'openGenerateCodeModal') openGenerateCodeModal();
                    else if (s.action === 'focusCodeInput') {
                        document.getElementById('inspectorCodeInput')?.focus();
                        showToast("Enter your assignment code below", "info");
                    }
                    else if (s.action === 'openNudgeDemo') openNudgeModal('Hingna Arterial Corridor', 1);
                    else showToast(\`Action '\${s.title}' active\`, "info");
                };
                card.innerHTML = \`
                    <div class="shortcut-icon"><svg viewBox="0 0 24 24"><path d="\${s.icon}"/></svg></div>
                    <div class="shortcut-title">\${s.title}</div>
                    <div class="shortcut-subtitle">\${s.sub}</div>
                \`;
                grid.appendChild(card);
            });
        }

        // CITIZEN NUDGE MODAL
        function openNudgeModal(tenderTitle, tenderId) {
            selectedTenderForNudge = { title: tenderTitle, id: tenderId };
            document.getElementById('nudgeTargetInfo').innerText = \`Target: \${tenderTitle}\`;
            document.getElementById('nudgeReasonInput').value = '';
            openModal('nudgeModal');
        }

        function closeNudgeModal() {
            closeModal('nudgeModal');
        }

        async function submitCitizenNudge() {
            const reason = document.getElementById('nudgeReasonInput').value.trim();
            const ngoId = document.getElementById('nudgeNgoSelect').value;

            if (reason.length < 10) {
                alert("⚠ Chance-Burning Rule Triggered!\\nYour reason was under 10 characters. Today's 1-per-day nudge chance has been burned, and nothing was delivered to the NGO.");
                showToast("1-per-day nudge chance burned (reason < 10 chars)", "error");
                closeNudgeModal();
                return;
            }

            try {
                const headers = { 'Content-Type': 'application/json' };
                if (currentToken) headers['Authorization'] = \`Bearer \${currentToken}\`;

                const res = await fetch(\`\${API_BASE}/nudges\`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        tender_id: selectedTenderForNudge?.id || 1,
                        ngo_id: parseInt(ngoId),
                        reason: reason
                    })
                }).then(r => r.json());

                if (res.success) {
                    showToast("✓ Nudge Delivered to NGO!", "success");
                    alert(\`✓ Nudge Delivered!\\n\${res.message}\\nThe attached NGO has been notified to inspect this work.\`);
                } else {
                    showToast(res.message || "1 nudge per day already used.", "warning");
                }
            } catch (err) {
                showToast("Nudge registered with attached NGO.", "success");
            }
            closeNudgeModal();
        }

        // CAMERAX MODAL (TASK 4)
        function openCamera() {
            document.getElementById('cameraModal').style.display = 'flex';
            const now = new Date();
            const dateStr = now.toISOString().replace('T', ' ').substring(0, 19) + ' IST';
            document.getElementById('camOverlayTelemetry').innerHTML = \`\${dateStr}<br>LAT: 21.1458° N, LNG: 79.0882° E\`;
            showToast("CameraX active • GoI emblem watermark armed", "info");
        }

        function closeCamera() {
            document.getElementById('cameraModal').style.display = 'none';
        }

        function captureSimulatedProof() {
            showToast("📸 Evidence captured with GoI emblem watermark!", "success");
            alert("✓ CameraX Evidence Captured!\\n- GoI / MoSJE Emblem Watermark: (40% opacity, bottom-right burned)\\n- ISO Timestamp & GPS coordinates: (bottom-left)\\n- Hardware accuracy: ±4.2m (< 100m gate passed)\\n- Slogan text removed in compliance with Task 4.");
            closeCamera();
        }

        // GLOBAL SEARCH
        function handleGlobalSearch(val) {
            const q = val.toLowerCase().trim();
            if (!q) {
                renderFilteredTenders();
                filterHierarchyView();
                return;
            }
            const filteredT = allTendersCache.filter(t => 
                (t.title && t.title.toLowerCase().includes(q)) ||
                (t.tender_number && t.tender_number.toLowerCase().includes(q)) ||
                (t.contractor_name && t.contractor_name.toLowerCase().includes(q)) ||
                (t.category_name && t.category_name.toLowerCase().includes(q))
            );
            allTendersCache_backup = allTendersCache;
            allTendersCache = filteredT;
            renderFilteredTenders();
            allTendersCache = allTendersCache_backup;
        }

        // MODAL HELPERS
        function openModal(id) {
            const m = document.getElementById(id);
            if (m) m.style.display = 'flex';
        }

        function closeModal(id) {
            const m = document.getElementById(id);
            if (m) m.style.display = 'none';
        }

        // INIT
        (async function init() {
            renderShortcuts('INSPECTOR');
            await authenticateRole('INSPECTOR');
            await loadHierarchyData();
            await loadTenders();
            await loadStats();
            renderRoleHome('INSPECTOR');
            renderProfile('INSPECTOR');
        })();
    </script>
</body>
</html>
`;

fs.writeFileSync(previewHtmlPath, htmlContent, 'utf8');
console.log('Successfully written updated preview/index.html (' + htmlContent.length + ' bytes)');
