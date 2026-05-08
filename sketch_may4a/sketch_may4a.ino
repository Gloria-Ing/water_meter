#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>
#include <SoftwareSerial.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <EEPROM.h>

// =====================================================
// VERSION
// =====================================================
#define VERSION "3.0"

// =====================================================
// WiFi CONFIGURATION
// =====================================================
const char* ssid = "KWIZERA";
const char* password = "AMOTECH123";

// =====================================================
// SERVER CONFIGURATION - UPDATE THIS IP
// =====================================================
const char* serverHost = "192.168.1.151";  // CHANGE TO YOUR COMPUTER'S IP
const int serverPort = 80;
const char* basePath = "/water_meter/api/";

// =====================================================
// DEVICE CONFIGURATION (Matches Database)
// =====================================================
const char* deviceId = "WATER_METER_006";
const int userId = 6;
const char* apiKey = "BLISS_001";

// =====================================================
// GSM SIM800C CONFIGURATION (Optional)
// =====================================================
#define SIM800_TX D6      // SIM800 RX pin
#define SIM800_RX D7      // SIM800 TX pin
#define USE_GSM false      // Set to true if SIM800 is connected

// =====================================================
// LCD I2C CONFIGURATION
// =====================================================
#define I2C_ADDRESS 0x27   // Common addresses: 0x27 or 0x3F
#define LCD_COLUMNS 16
#define LCD_ROWS 2

// =====================================================
// HARDWARE CONFIGURATION
// =====================================================
#define FLOW_SENSOR_PIN D5

// =====================================================
// CALIBRATION
// =====================================================
const float PULSE_PER_LITER = 7.5;  // Adjust based on your flow sensor

// =====================================================
// TIMING CONFIGURATION (milliseconds)
// =====================================================
const unsigned long MEASURE_INTERVAL = 10000;      // Measure every 10 seconds
const unsigned long SEND_INTERVAL = 30000;         // Send every 30 seconds
const unsigned long SMS_CHECK_INTERVAL = 60000;    // Check SMS every minute
const unsigned long LCD_UPDATE_INTERVAL = 2000;    // Update LCD every 2 seconds
const unsigned long WIFI_RETRY_INTERVAL = 30000;   // Retry WiFi every 30 seconds
const unsigned long EEPROM_SAVE_INTERVAL = 3600000; // Save to EEPROM every hour
const unsigned long DAILY_RESET_INTERVAL = 86400000; // Reset daily every 24 hours

// =====================================================
// GLOBAL VARIABLES
// =====================================================
volatile unsigned long pulseCount = 0;
float sessionLiters = 0;
float totalLiters = 0;
float dailyLiters = 0;
float currentFlowRate = 0;

unsigned long lastMeasure = 0;
unsigned long lastSend = 0;
unsigned long lastSmsCheck = 0;
unsigned long lastLcdUpdate = 0;
unsigned long lastWifiCheck = 0;
unsigned long lastEepromSave = 0;
unsigned long lastDailyReset = 0;

bool wifiConnected = false;
bool gsmInitialized = false;
bool lcdAvailable = false;

// LCD Display Mode
int displayMode = 0;
unsigned long lastDisplaySwitch = 0;

// WiFi Client
WiFiClient wifiClient;

// Objects
LiquidCrystal_I2C lcd(I2C_ADDRESS, LCD_COLUMNS, LCD_ROWS);
SoftwareSerial sim800(SIM800_RX, SIM800_TX);

// =====================================================
// EEPROM ADDRESSES
// =====================================================
const int EEPROM_SIZE = 512;
const int EEPROM_TOTAL_LITERS_ADDR = 0;
const int EEPROM_DAILY_LITERS_ADDR = 40;
const int EEPROM_LAST_DAILY_RESET_ADDR = 80;
const int EEPROM_MAGIC_ADDR = 500;
const int MAGIC_NUMBER = 0xABCD;

// =====================================================
// INTERRUPT HANDLER
// =====================================================
void ICACHE_RAM_ATTR onPulse() {
    pulseCount++;
}

// =====================================================
// LCD FUNCTIONS
// =====================================================
void initLCD() {
    Serial.println("📺 Initializing I2C LCD...");
    
    Wire.begin(D1, D2);  // SDA = D1 (GPIO5), SCL = D2 (GPIO4)
    
    // Scan for I2C devices
    byte error, address;
    int nDevices = 0;
    
    for(address = 1; address < 127; address++) {
        Wire.beginTransmission(address);
        error = Wire.endTransmission();
        
        if (error == 0) {
            Serial.print("   I2C device found at 0x");
            if (address < 16) Serial.print("0");
            Serial.println(address, HEX);
            nDevices++;
        }
    }
    
    if (nDevices == 0) {
        Serial.println("   ❌ No I2C devices found!");
        lcdAvailable = false;
        return;
    }
    
    // Initialize LCD
    lcd.init();
    lcd.backlight();
    lcdAvailable = true;
    Serial.println("✅ LCD initialized");
    
    // Welcome message
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Water Meter v");
    lcd.print(VERSION);
    lcd.setCursor(0, 1);
    lcd.print("Initializing...");
    delay(2000);
}

void updateLCD() {
    if (!lcdAvailable) return;
    
    // Switch display mode every 5 seconds
    if (millis() - lastDisplaySwitch >= 5000) {
        displayMode = (displayMode + 1) % 4;
        lastDisplaySwitch = millis();
    }
    
    lcd.clear();
    
    switch(displayMode) {
        case 0: { // Total usage and bill
            float totalBill = (totalLiters / 1000) * 100;
            lcd.setCursor(0, 0);
            lcd.print("T:");
            lcd.print(totalLiters, 0);
            lcd.print("L");
            
            lcd.setCursor(8, 0);
            lcd.print("Bill:");
            lcd.print(totalBill, 0);
            
            lcd.setCursor(0, 1);
            lcd.print("Flow:");
            lcd.print(currentFlowRate, 2);
            lcd.print("L/s");
            break;
        }
        
        case 1: { // Daily usage
            float dailyBill = (dailyLiters / 1000) * 100;
            lcd.setCursor(0, 0);
            lcd.print("Today:");
            lcd.print(dailyLiters, 0);
            lcd.print("L");
            
            lcd.setCursor(0, 1);
            lcd.print("Bill:");
            lcd.print(dailyBill, 0);
            lcd.print("RWF");
            break;
        }
        
        case 2: { // Status
            lcd.setCursor(0, 0);
            lcd.print("WiFi:");
            lcd.print(wifiConnected ? "OK " : "OFF");
            lcd.setCursor(8, 0);
            lcd.print("GSM:");
            lcd.print(gsmInitialized ? "OK" : "OFF");
            
            lcd.setCursor(0, 1);
            lcd.print("Dev:");
            lcd.print(deviceId);
            break;
        }
        
        case 3: { // Device info
            lcd.setCursor(0, 0);
            lcd.print("User ID:");
            lcd.print(userId);
            
            lcd.setCursor(0, 1);
            lcd.print("Server:");
            lcd.print(serverHost);
            break;
        }
    }
}

void displayMessageLCD(String line1, String line2, int duration = 2000) {
    if (!lcdAvailable) return;
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print(line1);
    lcd.setCursor(0, 1);
    lcd.print(line2);
    delay(duration);
}

// =====================================================
// WIFI FUNCTIONS
// =====================================================
void connectToWiFi() {
    Serial.println("📡 Connecting to WiFi...");
    displayMessageLCD("Connecting", "WiFi...", 0);
    
    WiFi.begin(ssid, password);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 25) {
        delay(500);
        attempts++;
        Serial.print(".");
        
        if (lcdAvailable && attempts % 4 == 0) {
            lcd.setCursor(12, 1);
            lcd.print(".");
        }
    }
    
    if (WiFi.status() == WL_CONNECTED) {
        wifiConnected = true;
        Serial.println("\n✅ WiFi connected!");
        Serial.print("   IP: ");
        Serial.println(WiFi.localIP());
        Serial.print("   RSSI: ");
        Serial.println(WiFi.RSSI());
        
        String ipStr = WiFi.localIP().toString();
        displayMessageLCD("WiFi OK", ipStr, 1500);
    } else {
        wifiConnected = false;
        Serial.println("\n❌ WiFi failed!");
        displayMessageLCD("WiFi Failed", "Check credentials", 2000);
    }
}

void checkWiFiConnection() {
    if (millis() - lastWifiCheck >= WIFI_RETRY_INTERVAL) {
        if (WiFi.status() != WL_CONNECTED) {
            Serial.println("📡 WiFi disconnected, reconnecting...");
            connectToWiFi();
        }
        lastWifiCheck = millis();
    }
}

// =====================================================
// GSM SIM800C FUNCTIONS
// =====================================================
void initSIM800() {
    if (!USE_GSM) {
        Serial.println("📱 GSM disabled");
        gsmInitialized = false;
        return;
    }
    
    Serial.println("📱 Initializing SIM800C...");
    displayMessageLCD("SIM800C", "Init...", 0);
    
    sim800.begin(9600);
    delay(2000);
    
    // Clear buffer
    while(sim800.available()) sim800.read();
    
    // Test AT commands
    String response = sendATCommand("AT", 1000);
    if (response.indexOf("OK") > 0) {
        Serial.println("✅ SIM800C detected");
        gsmInitialized = true;
        
        sendATCommand("AT+CMGF=1", 1000);      // SMS text mode
        sendATCommand("AT+CNMI=2,2,0,0,0", 1000);
        sendATCommand("AT+CSCS=\"GSM\"", 1000);
        
        response = sendATCommand("AT+CSQ", 1000);
        Serial.print("   Signal: ");
        Serial.println(response);
        
        displayMessageLCD("SIM800 OK", "Signal Ready", 1500);
    } else {
        Serial.println("❌ SIM800C not detected!");
        gsmInitialized = false;
        displayMessageLCD("SIM800 Error", "Check wiring", 2000);
    }
}

String sendATCommand(String command, int timeout) {
    sim800.println(command);
    delay(100);
    
    String response = "";
    unsigned long startTime = millis();
    
    while (millis() - startTime < timeout) {
        while (sim800.available()) {
            char c = sim800.read();
            response += c;
            delay(5);
        }
    }
    
    response.trim();
    if (response.length() > 0 && response.indexOf("AT") == -1) {
        Serial.println("   CMD: " + command);
        Serial.println("   RES: " + response);
    }
    
    return response;
}

bool sendSMS(String phoneNumber, String message) {
    if (!gsmInitialized || !USE_GSM) return false;
    
    Serial.print("📨 Sending SMS to: ");
    Serial.println(phoneNumber);
    
    while(sim800.available()) sim800.read();
    
    sim800.println("AT+CMGF=1");
    delay(500);
    sim800.print("AT+CMGS=\"");
    sim800.print(phoneNumber);
    sim800.println("\"");
    delay(500);
    sim800.print(message);
    delay(500);
    sim800.write(26);
    delay(5000);
    
    String response = "";
    unsigned long startTime = millis();
    while (millis() - startTime < 3000) {
        while (sim800.available()) {
            char c = sim800.read();
            response += c;
        }
    }
    
    return (response.indexOf("OK") > 0 || response.indexOf("+CMGS") > 0);
}

// =====================================================
// SERVER COMMUNICATION
// =====================================================
bool pingServer() {
    if (!wifiConnected) return false;
    
    HTTPClient http;
    String url = "http://" + String(serverHost) + ":" + String(serverPort) + basePath + "test_api.php";
    
    http.begin(wifiClient, url);
    int httpCode = http.GET();
    http.end();
    
    return (httpCode == 200);
}

void sendDataToServer() {
    if (!wifiConnected) {
        Serial.println("❌ WiFi not connected");
        return;
    }
    
    if (sessionLiters == 0) {
        // Send heartbeat every 5 minutes
        static unsigned long lastHeartbeat = 0;
        if (millis() - lastHeartbeat >= 300000) {
            sendHeartbeat();
            lastHeartbeat = millis();
        }
        return;
    }
    
    HTTPClient http;
    String url = "http://" + String(serverHost) + ":" + String(serverPort) + basePath + "record_usage.php";
    
    Serial.println("────────────────────────────────────────");
    Serial.println("📤 SENDING DATA");
    Serial.print("   URL: ");
    Serial.println(url);
    Serial.print("   Device: ");
    Serial.println(deviceId);
    Serial.print("   User: ");
    Serial.println(userId);
    Serial.print("   Liters: ");
    Serial.println(sessionLiters);
    
    displayMessageLCD("Sending", String(sessionLiters) + " L", 0);
    
    http.begin(wifiClient, url);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("X-Device-Id", deviceId);
    http.addHeader("X-API-Key", apiKey);
    
    String postData = "user_id=" + String(userId) + "&liters=" + String(sessionLiters);
    int httpCode = http.POST(postData);
    
    if (httpCode == 200) {
        String response = http.getString();
        Serial.print("   Response: ");
        Serial.println(response);
        
        StaticJsonDocument<200> doc;
        DeserializationError error = deserializeJson(doc, response);
        
        if (!error && doc["success"]) {
            float bill = doc["bill"];
            float balance = doc["total_balance"];
            Serial.println("   ✅ DATA SENT!");
            Serial.print("   Bill: ");
            Serial.print(bill);
            Serial.println(" RWF");
            Serial.print("   Balance: ");
            Serial.print(balance);
            Serial.println(" RWF");
            
            displayMessageLCD("Sent!", "Bill: " + String(bill) + " RWF", 1500);
            sessionLiters = 0;
        }
    } else {
        Serial.print("   ❌ Failed: HTTP ");
        Serial.println(httpCode);
        displayMessageLCD("Failed", "HTTP " + String(httpCode), 1500);
    }
    
    http.end();
    Serial.println("────────────────────────────────────────");
}

void sendHeartbeat() {
    if (!wifiConnected) return;
    
    HTTPClient http;
    String url = "http://" + String(serverHost) + ":" + String(serverPort) + basePath + "heartbeat.php";
    
    http.begin(wifiClient, url);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("X-Device-Id", deviceId);
    
    String postData = "user_id=" + String(userId) + 
                      "&total_liters=" + String(totalLiters) +
                      "&daily_liters=" + String(dailyLiters) +
                      "&flow_rate=" + String(currentFlowRate) +
                      "&rssi=" + String(WiFi.RSSI());
    
    int httpCode = http.POST(postData);
    if (httpCode == 200) {
        Serial.println("💓 Heartbeat sent");
    }
    http.end();
}

// =====================================================
// FLOW MEASUREMENT
// =====================================================
void measureWater() {
    if (pulseCount > 0) {
        noInterrupts();
        unsigned long count = pulseCount;
        pulseCount = 0;
        interrupts();
        
        float liters = count / PULSE_PER_LITER;
        totalLiters += liters;
        sessionLiters += liters;
        dailyLiters += liters;
        currentFlowRate = liters / (MEASURE_INTERVAL / 1000.0);
        
        Serial.print("💧 Flow: ");
        Serial.print(liters, 2);
        Serial.print(" L | Rate: ");
        Serial.print(currentFlowRate, 2);
        Serial.print(" L/s | Total: ");
        Serial.print(totalLiters, 2);
        Serial.println(" L");
        
        // High flow alert
        if (currentFlowRate > 0.5) {
            Serial.println("⚠️ HIGH FLOW!");
            if (gsmInitialized) {
                sendSMS("0788000001", "HIGH FLOW: " + String(currentFlowRate) + " L/s");
            }
        }
        
        // Save to EEPROM
        if (millis() - lastEepromSave >= EEPROM_SAVE_INTERVAL) {
            saveData();
            lastEepromSave = millis();
        }
    }
}

// =====================================================
// EEPROM FUNCTIONS
// =====================================================
void saveData() {
    EEPROM.put(EEPROM_TOTAL_LITERS_ADDR, totalLiters);
    EEPROM.put(EEPROM_DAILY_LITERS_ADDR, dailyLiters);
    EEPROM.put(EEPROM_LAST_DAILY_RESET_ADDR, lastDailyReset);
    EEPROM.put(EEPROM_MAGIC_ADDR, MAGIC_NUMBER);
    EEPROM.commit();
    Serial.println("💾 Data saved to EEPROM");
}

void loadData() {
    int magic;
    EEPROM.get(EEPROM_MAGIC_ADDR, magic);
    
    if (magic == MAGIC_NUMBER) {
        EEPROM.get(EEPROM_TOTAL_LITERS_ADDR, totalLiters);
        EEPROM.get(EEPROM_DAILY_LITERS_ADDR, dailyLiters);
        EEPROM.get(EEPROM_LAST_DAILY_RESET_ADDR, lastDailyReset);
        
        Serial.print("📂 Loaded: Total=");
        Serial.print(totalLiters, 2);
        Serial.print(" L, Daily=");
        Serial.print(dailyLiters, 2);
        Serial.println(" L");
    } else {
        totalLiters = 0;
        dailyLiters = 0;
        lastDailyReset = millis();
        Serial.println("📂 No saved data");
    }
}

void resetDailyIfNeeded() {
    if (millis() - lastDailyReset >= DAILY_RESET_INTERVAL) {
        Serial.println("═══════════════════════════════════════");
        Serial.print("📅 DAILY RESET - Used: ");
        Serial.print(dailyLiters, 2);
        Serial.println(" L");
        Serial.println("═══════════════════════════════════════");
        
        dailyLiters = 0;
        lastDailyReset = millis();
        saveData();
    }
}

// =====================================================
// SMS HANDLING
// =====================================================
void checkForSMS() {
    if (!gsmInitialized || !USE_GSM) return;
    
    sim800.println("AT+CMGL=\"ALL\"");
    delay(2000);
    
    String response = "";
    while (sim800.available()) {
        char c = sim800.read();
        response += c;
    }
    
    if (response.indexOf("+CMGL") > 0) {
        Serial.println("📨 New SMS received");
        
        if (response.indexOf("STATUS") > 0) {
            String reply = "WATER METER\nTotal: " + String(totalLiters) + "L\nToday: " + String(dailyLiters) + "L";
            sendSMS("0788000001", reply);
        }
    }
}

// =====================================================
// TEST MODE (Simulate water flow for testing)
// =====================================================
void simulateWaterFlow() {
    static unsigned long lastSimulate = 0;
    if (millis() - lastSimulate >= 60000) {
        pulseCount += 75;  // 10 liters
        lastSimulate = millis();
        Serial.println("🔧 TEST: Simulating 10L flow");
    }
}

// =====================================================
// DIAGNOSTICS
// =====================================================
void printDiagnostics() {
    Serial.println("\n═══════════════════════════════════════");
    Serial.println("🔧 SYSTEM DIAGNOSTICS");
    Serial.println("═══════════════════════════════════════");
    Serial.print("Version: ");
    Serial.println(VERSION);
    Serial.print("Device ID: ");
    Serial.println(deviceId);
    Serial.print("User ID: ");
    Serial.println(userId);
    Serial.print("Server: ");
    Serial.print(serverHost);
    Serial.print(":");
    Serial.println(serverPort);
    Serial.print("WiFi: ");
    Serial.println(wifiConnected ? "Connected" : "Disconnected");
    Serial.print("GSM: ");
    Serial.println(gsmInitialized ? "Ready" : "Not ready");
    Serial.print("LCD: ");
    Serial.println(lcdAvailable ? "Available" : "Not available");
    Serial.print("Total Liters: ");
    Serial.println(totalLiters);
    Serial.print("Daily Liters: ");
    Serial.println(dailyLiters);
    Serial.print("Free Heap: ");
    Serial.println(ESP.getFreeHeap());
    Serial.println("═══════════════════════════════════════\n");
}

// =====================================================
// SETUP
// =====================================================
void setup() {
    Serial.begin(115200);
    Serial.println("\n╔═══════════════════════════════════════╗");
    Serial.print("║   Water Meter IoT System v");
    Serial.print(VERSION);
    Serial.println("          ║");
    Serial.println("║   Device: WATER_METER_006            ║");
    Serial.println("║   User ID: 6                         ║");
    Serial.println("╚═══════════════════════════════════════╝\n");
    
    // Initialize EEPROM
    EEPROM.begin(EEPROM_SIZE);
    loadData();
    
    // Initialize LCD
    initLCD();
    
    // Initialize Flow Sensor
    pinMode(FLOW_SENSOR_PIN, INPUT_PULLUP);
    attachInterrupt(digitalPinToInterrupt(FLOW_SENSOR_PIN), onPulse, FALLING);
    Serial.println("✅ Flow sensor initialized");
    
    // Initialize GSM
    initSIM800();
    
    // Connect to WiFi
    connectToWiFi();
    
    // Initialize timing
    lastMeasure = millis();
    lastSend = millis();
    lastSmsCheck = millis();
    lastLcdUpdate = millis();
    lastWifiCheck = millis();
    lastEepromSave = millis();
    
    displayMessageLCD("System Ready", deviceId, 2000);
    
    printDiagnostics();
    
    // Send startup test data
    delay(2000);
    sessionLiters = 5;  // Send 5L test data
    sendDataToServer();
}

// =====================================================
// MAIN LOOP
// =====================================================
void loop() {
    unsigned long now = millis();
    
    // Measure water flow
    if (now - lastMeasure >= MEASURE_INTERVAL) {
        measureWater();
        lastMeasure = now;
    }
    
    // Update LCD
    if (now - lastLcdUpdate >= LCD_UPDATE_INTERVAL) {
        updateLCD();
        lastLcdUpdate = now;
    }
    
    // Send data to server
    if (now - lastSend >= SEND_INTERVAL) {
        sendDataToServer();
        lastSend = now;
    }
    
    // Check for SMS
    if (now - lastSmsCheck >= SMS_CHECK_INTERVAL && gsmInitialized && USE_GSM) {
        checkForSMS();
        lastSmsCheck = now;
    }
    
    // Check WiFi
    checkWiFiConnection();
    
    // Daily reset
    resetDailyIfNeeded();
    
    // Optional: Simulate water flow for testing (uncomment to enable)
    // simulateWaterFlow();
    
    delay(10);
}