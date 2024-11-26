/****************************************************************************
*
Project Name: Smart Home Automation
Tyler Mayou
11/26/2024
ESP32 - Bluetooth, LEDC, Speaker Control, Temp/Humidity Sensor
*
****************************************************************************/

//All necessary include files
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <inttypes.h>
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/event_groups.h"
#include "esp_system.h"
#include "esp_log.h"
#include "nvs_flash.h"
#include "esp_bt.h"
#include "esp_gap_ble_api.h"
#include "esp_gatts_api.h"
#include "esp_bt_defs.h"
#include "esp_bt_main.h"
#include "esp_bt_device.h"
#include "esp_gatt_common_api.h"
#include "sdkconfig.h"
#include "driver/gpio.h"
#include "driver/ledc.h"
#include "dht.h"
#include "freertos/timers.h"

//Log Tag
#define GATTS_TAG "BLUETOOTH"

//Variables for temperature sensor
int16_t temperature = 0;
int16_t humidity = 0;

///Declare the static function
static void gatts_profile_a_event_handler(esp_gatts_cb_event_t event, esp_gatt_if_t gatts_if, esp_ble_gatts_cb_param_t *param);
//static void gatts_profile_b_event_handler(esp_gatts_cb_event_t event, esp_gatt_if_t gatts_if, esp_ble_gatts_cb_param_t *param);

//define Service UUID, Characteristic UUID, Descriptor UUID, and Handle number
#define GATTS_SERVICE_UUID_A   0x00FF
#define GATTS_CHAR_UUID_A      0xFF01
#define GATTS_DESCR_UUID_A     0x3333
#define GATTS_NUM_HANDLE_A     4

//set device name
#define DEVICE_NAME            "SMART HOME AUTOMATION - ESP32"
#define MANUFACTURER_DATA_LEN  17

//set max length for characteristic
#define GATTS_CHAR_VAL_LEN_MAX 0x40

//set prepare max buffer size
#define PREPARE_BUF_MAX_SIZE 1024

// Declare a static value for storing the characteristic value
static uint8_t char1_str[] = {0x11, 0x22, 0x33}; // This defines an array 'char1_str' with 3 bytes of data (0x11, 0x22, and 0x33) that will be used as the characteristic value.
static esp_gatt_char_prop_t a_property = 0; // Declare a variable 'a_property' that holds the characteristic's properties. It is initially set to 0 (no properties set yet).

// Set the attribute value type
static esp_attr_value_t gatts_char1_val =
{
    .attr_max_len = GATTS_CHAR_VAL_LEN_MAX, // The maximum length of the attribute value. GATTS_CHAR_VAL_LEN_MAX is typically a predefined constant that specifies the maximum allowed size.
    .attr_len     = sizeof(char1_str), // The actual length of the attribute value, which is the size of 'char1_str' in bytes.
    .attr_value   = char1_str, // The attribute value itself, pointing to the 'char1_str' array which contains the actual data to be associated with the characteristic.
};

// Variable to track if the advertising configuration is done
static uint8_t adv_config_done = 0; // Initially set to 0, used to track if the advertising configuration is complete.

#define adv_config_flag      (1 << 0) // Define a flag to indicate if the advertising data configuration is done. It is the first bit.
#define scan_rsp_config_flag (1 << 1) // Define a flag to indicate if the scan response configuration is done. It is the second bit.

// Condition to check if raw advertising data should be used
#ifdef CONFIG_SET_RAW_ADV_DATA

// Define raw advertising data if the CONFIG_SET_RAW_ADV_DATA macro is enabled
static uint8_t raw_adv_data[] = {
        0x02, 0x01, 0x06,                  // Length 2, Data Type 1 (Flags), Data 1 (LE General Discoverable Mode, BR/EDR Not Supported)
        0x02, 0x0a, 0xeb,                  // Length 2, Data Type 10 (TX power level), Data 2 (-21 dBm)
        0x03, 0x03, 0xab, 0xcd,            // Length 3, Data Type 3 (Complete 16-bit Service UUIDs), Data 3 (UUID 0xABCD)
};
static uint8_t raw_scan_rsp_data[] = {     // Length 15, Data Type 9 (Complete Local Name), Data 1 (ESP_GATTS_DEMO)
        0x0f, 0x09, 0x45, 0x53, 0x50, 0x5f, 0x47, 0x41, 0x54, 0x54, 0x53, 0x5f, 0x44,
        0x45, 0x4d, 0x4f
};
#else

// Define 128-bit service UUID (used when raw advertising data is not enabled)
static uint8_t adv_service_uuid128[16] = {
    /* LSB <--------------------------------------------------------------------------------> MSB */
    // First UUID (16-bit), represented by two bytes: [12] and [13] are the values for the first part of the UUID
    0xfb, 0x34, 0x9b, 0x5f, 0x80, 0x00, 0x00, 0x80, 0x00, 0x10, 0x00, 0x00, 0xEE, 0x00, 0x00, 0x00
    // Second UUID (32-bit), the last 4 bytes [12], [13], [14], and [15] would be used for the second part of the UUID.
    // 0xfb, 0x34, 0x9b, 0x5f, 0x80, 0x00, 0x00, 0x80, 0x00, 0x10, 0x00, 0x00, 0xFF, 0x00, 0x00, 0x00,
};
#endif


// The length of adv data must be less than 31 bytes
//static uint8_t test_manufacturer[MANUFACTURER_DATA_LEN] =  {0x12, 0x23, 0x45, 0x56};
// Advertising data structure
static esp_ble_adv_data_t adv_data = {
    .set_scan_rsp = false,                     // No scan response data (false means not a scan response)
    .include_name = true,                      // Include the device name in the advertisement
    .include_txpower = false,                  // Do not include the TX power in the advertisement
    .min_interval = 0x0006,                    // Minimum connection interval for the slave, Time = min_interval * 1.25 msec
    .max_interval = 0x0010,                    // Maximum connection interval for the slave, Time = max_interval * 1.25 msec
    .appearance = 0x00,                        // Appearance of the device (used for specific BLE devices, like wearable, etc.)
    .manufacturer_len = 0,                     // Length of manufacturer data (set to 0, no manufacturer data is used here)
    .p_manufacturer_data = NULL,               // Pointer to the manufacturer data (no data, so NULL)
    .service_data_len = 0,                     // Length of service-specific data (none in this case)
    .p_service_data = NULL,                    // Pointer to the service-specific data (none in this case)
    .service_uuid_len = sizeof(adv_service_uuid128),  // Length of the service UUID (128-bit UUID)
    .p_service_uuid = adv_service_uuid128,    // Pointer to the service UUID (a 128-bit UUID)
    .flag = (ESP_BLE_ADV_FLAG_GEN_DISC | ESP_BLE_ADV_FLAG_BREDR_NOT_SPT), // Flags for advertising: General discoverable mode and no support for BR/EDR (Basic Rate/Enhanced Data Rate)
};

// Scan response data structure
static esp_ble_adv_data_t scan_rsp_data = {
    .set_scan_rsp = true,                      // This is a scan response, so it's set to true
    .include_name = true,                      // Include the device name in the scan response
    .include_txpower = true,                   // Include the TX power in the scan response
    .appearance = 0x00,                        // Appearance of the device (same as in adv_data)
    .manufacturer_len = 0,                     // No manufacturer data included in the scan response
    .p_manufacturer_data = NULL,               // No manufacturer data
    .service_data_len = 0,                     // No service-specific data
    .p_service_data = NULL,                    // No service-specific data
    .service_uuid_len = sizeof(adv_service_uuid128), // 128-bit service UUID length
    .p_service_uuid = adv_service_uuid128,    // Pointer to the service UUID (same as in adv_data)
    .flag = (ESP_BLE_ADV_FLAG_GEN_DISC | ESP_BLE_ADV_FLAG_BREDR_NOT_SPT), // Same flags as in adv_data
};

//#endif /* CONFIG_SET_RAW_ADV_DATA */

// Advertising parameters
static esp_ble_adv_params_t adv_params = {
    .adv_int_min        = 0x20, // Minimum advertising interval (0x20 = 32 * 0.625 ms = 20 ms)
    .adv_int_max        = 0x40, // Maximum advertising interval (0x40 = 64 * 0.625 ms = 40 ms)
    .adv_type           = ADV_TYPE_IND, // Advertising type: ADV_TYPE_IND (indirect advertising)
    .own_addr_type      = BLE_ADDR_TYPE_PUBLIC, // Device's address type: public address
    .channel_map        = ADV_CHNL_ALL, // Use all 3 advertising channels
    .adv_filter_policy  = ADV_FILTER_ALLOW_SCAN_ANY_CON_ANY, // Advertising filter policy: allow any scanning and connecting
};

#define PROFILE_NUM 2
#define PROFILE_A_APP_ID 0

// GATT profile structure for handling different services and characteristics
struct gatts_profile_inst {
    esp_gatts_cb_t gatts_cb;             // Callback function for GATT events
    uint16_t gatts_if;                   // GATT interface (GATT server instance)
    uint16_t app_id;                     // Application ID for the profile
    uint16_t conn_id;                    // Connection ID for active connections
    uint16_t service_handle;             // Handle for the GATT service
    esp_gatt_srvc_id_t service_id;       // Service ID for the GATT service
    uint16_t char_handle;                // Handle for the characteristic
    esp_bt_uuid_t char_uuid;             // UUID of the characteristic
    esp_gatt_perm_t perm;                // Permissions for the characteristic
    esp_gatt_char_prop_t property;       // Properties of the characteristic
    uint16_t descr_handle;               // Handle for the descriptor
    esp_bt_uuid_t descr_uuid;            // UUID of the descriptor
};

// Array for storing GATT profile instances (supports multiple profiles)
static struct gatts_profile_inst gl_profile_tab[PROFILE_NUM] = {
    [PROFILE_A_APP_ID] = {
        .gatts_cb = gatts_profile_a_event_handler, // Event handler for PROFILE_A
        .gatts_if = ESP_GATT_IF_NONE,              // Initial GATT interface is set to none
    },
};

// Structure to hold data for a prepared write environment (for handling GATT write operations)
typedef struct {
    uint8_t *prepare_buf;     // Buffer to store prepared data for writing
    int prepare_len;          // Length of the prepared data
} prepare_type_env_t;

// Instance of the prepared write environment for profile A
static prepare_type_env_t a_prepare_write_env;

// Function prototype for handling write events in the GATT server
void example_write_event_env(esp_gatt_if_t gatts_if, prepare_type_env_t *prepare_write_env, esp_ble_gatts_cb_param_t *param);

// Function prototype for handling the execution of a write operation in the GATT server
void example_exec_write_event_env(prepare_type_env_t *prepare_write_env, esp_ble_gatts_cb_param_t *param);

// Generic Access Profile (GAP) event handler for handling BLE advertisement, scan responses, and connection parameters
static void gap_event_handler(esp_gap_ble_cb_event_t event, esp_ble_gap_cb_param_t *param) {
    switch (event) {
#ifdef CONFIG_SET_RAW_ADV_DATA
    // If raw advertisement data is set, handle the related events for raw advertisement and scan response data setup
    case ESP_GAP_BLE_ADV_DATA_RAW_SET_COMPLETE_EVT:
        adv_config_done &= (~adv_config_flag);  // Clear the advertisement data flag
        // If both advertisement and scan response data are set, start advertising
        if (adv_config_done == 0) {
            esp_ble_gap_start_advertising(&adv_params);  // Start BLE advertising with the configured parameters
        }
        break;
    case ESP_GAP_BLE_SCAN_RSP_DATA_RAW_SET_COMPLETE_EVT:
        adv_config_done &= (~scan_rsp_config_flag);  // Clear the scan response data flag
        // If both advertisement and scan response data are set, start advertising
        if (adv_config_done == 0) {
            esp_ble_gap_start_advertising(&adv_params);  // Start BLE advertising with the configured parameters
        }
        break;
#else
    // If raw advertisement data is not used, handle the standard advertisement and scan response data setup events
    case ESP_GAP_BLE_ADV_DATA_SET_COMPLETE_EVT:
        adv_config_done &= (~adv_config_flag);  // Clear the advertisement data flag
        // If both advertisement and scan response data are set, start advertising
        if (adv_config_done == 0) {
            esp_ble_gap_start_advertising(&adv_params);  // Start BLE advertising with the configured parameters
        }
        break;
    case ESP_GAP_BLE_SCAN_RSP_DATA_SET_COMPLETE_EVT:
        adv_config_done &= (~scan_rsp_config_flag);  // Clear the scan response data flag
        // If both advertisement and scan response data are set, start advertising
        if (adv_config_done == 0) {
            esp_ble_gap_start_advertising(&adv_params);  // Start BLE advertising with the configured parameters
        }
        break;
#endif

    // Event triggered when advertising starts
    case ESP_GAP_BLE_ADV_START_COMPLETE_EVT:
        // Check the status of the advertising start event
        if (param->adv_start_cmpl.status != ESP_BT_STATUS_SUCCESS) {
            // If the status is not success, log an error
            ESP_LOGE(GATTS_TAG, "Advertising start failed");
        }
        break;

    // Event triggered when advertising stops
    case ESP_GAP_BLE_ADV_STOP_COMPLETE_EVT:
        // Check the status of the advertising stop event
        if (param->adv_stop_cmpl.status != ESP_BT_STATUS_SUCCESS) {
            // If the status is not success, log an error
            ESP_LOGE(GATTS_TAG, "Advertising stop failed");
        } else {
            // If the advertising stopped successfully, log a success message
            ESP_LOGI(GATTS_TAG, "Stop adv successfully");
        }
        break;

    // Event triggered when the connection parameters are updated
    case ESP_GAP_BLE_UPDATE_CONN_PARAMS_EVT:
        // Log the updated connection parameters (status, min/max connection interval, connection interval, latency, and timeout)
        ESP_LOGI(GATTS_TAG, "update connection params status = %d, min_int = %d, max_int = %d, conn_int = %d, latency = %d, timeout = %d",
                  param->update_conn_params.status,
                  param->update_conn_params.min_int,
                  param->update_conn_params.max_int,
                  param->update_conn_params.conn_int,
                  param->update_conn_params.latency,
                  param->update_conn_params.timeout);
        break;

    // Event triggered when the packet length is updated
    case ESP_GAP_BLE_SET_PKT_LENGTH_COMPLETE_EVT:
        // Log the updated packet length values (RX and TX lengths) and the status of the update
        ESP_LOGI(GATTS_TAG, "packet length updated: rx = %d, tx = %d, status = %d",
                  param->pkt_data_length_cmpl.params.rx_len,
                  param->pkt_data_length_cmpl.params.tx_len,
                  param->pkt_data_length_cmpl.status);
        break;

    // Default case for handling events not explicitly defined above
    default:
        break;
    }
}


// Function that handles the write event for a GATT server (with preparation support)
void example_write_event_env(esp_gatt_if_t gatts_if, prepare_type_env_t *prepare_write_env, esp_ble_gatts_cb_param_t *param) { 
    esp_gatt_status_t status = ESP_GATT_OK; // Initialize the status as success (OK)

    // Check if the write operation requires a response (need_rsp = 1 means response is required)
    if (param->write.need_rsp) {
        
        // Check if the write operation is a prepared write (is_prep = 1 means this is a prepared write)
        if (param->write.is_prep) {
            
            // Check if the offset of the write exceeds the maximum size allowed for prepared writes
            if (param->write.offset > PREPARE_BUF_MAX_SIZE) {
                status = ESP_GATT_INVALID_OFFSET;  // If the offset is too large, set error status
            } 
            // Check if the sum of the offset and length exceeds the maximum buffer size
            else if ((param->write.offset + param->write.len) > PREPARE_BUF_MAX_SIZE) {
                status = ESP_GATT_INVALID_ATTR_LEN;  // If the length exceeds the available buffer, set error status
            }

            // If no errors have occurred yet and the prepared buffer is not allocated
            if (status == ESP_GATT_OK && prepare_write_env->prepare_buf == NULL) {
                // Allocate memory for the buffer to hold the data for prepared write
                prepare_write_env->prepare_buf = (uint8_t *)malloc(PREPARE_BUF_MAX_SIZE * sizeof(uint8_t));
                prepare_write_env->prepare_len = 0; // Initialize the length to 0

                // Check if memory allocation succeeded
                if (prepare_write_env->prepare_buf == NULL) {
                    ESP_LOGE(GATTS_TAG, "Gatt_server prep no mem");  // Log error if memory allocation failed
                    status = ESP_GATT_NO_RESOURCES;  // Set the error status for no resources
                }
            }

            // Allocate memory for the GATT response structure
            esp_gatt_rsp_t *gatt_rsp = (esp_gatt_rsp_t *)malloc(sizeof(esp_gatt_rsp_t));
            if (gatt_rsp) {
                // Prepare the response structure with the write information
                gatt_rsp->attr_value.len = param->write.len;  // Set the length of the attribute value
                gatt_rsp->attr_value.handle = param->write.handle;  // Set the handle of the attribute
                gatt_rsp->attr_value.offset = param->write.offset;  // Set the offset of the write
                gatt_rsp->attr_value.auth_req = ESP_GATT_AUTH_REQ_NONE;  // No authentication required for this write
                // Copy the data being written into the response structure
                memcpy(gatt_rsp->attr_value.value, param->write.value, param->write.len);

                // Send the GATT response back to the client
                esp_err_t response_err = esp_ble_gatts_send_response(gatts_if, param->write.conn_id, param->write.trans_id, status, gatt_rsp);
                if (response_err != ESP_OK) {  // If sending the response fails, log the error
                    ESP_LOGE(GATTS_TAG, "Send response error\n");
                }

                // Free the allocated memory for the response structure
                free(gatt_rsp);
            } else {
                ESP_LOGE(GATTS_TAG, "malloc failed, no resource to send response error\n");  // Log error if malloc failed
                status = ESP_GATT_NO_RESOURCES;  // Set error status for memory allocation failure
            }

            // If there was an error sending the response, exit the function early
            if (status != ESP_GATT_OK) {
                return;
            }

            // Store the written data into the prepared buffer at the specified offset
            memcpy(prepare_write_env->prepare_buf + param->write.offset,
                   param->write.value,
                   param->write.len);
            // Update the length of the prepared data
            prepare_write_env->prepare_len += param->write.len;

        } else {  // If this is not a prepared write (normal write request)
            // Send a response indicating that the write was successful (status is already ESP_GATT_OK)
            esp_ble_gatts_send_response(gatts_if, param->write.conn_id, param->write.trans_id, status, NULL);
        }
    }
}

// Function that handles the execution of write requests in a GATT server environment
void example_exec_write_event_env(prepare_type_env_t *prepare_write_env, esp_ble_gatts_cb_param_t *param) {

    // Check if the execution flag indicates a successful write execution (ESP_GATT_PREP_WRITE_EXEC)
    if (param->exec_write.exec_write_flag == ESP_GATT_PREP_WRITE_EXEC) {
        // If successful, log the buffer content from the preparation environment (this shows the data written)
        esp_log_buffer_hex(GATTS_TAG, prepare_write_env->prepare_buf, prepare_write_env->prepare_len);
    } 
    else {
        // If the execution flag indicates a cancelation (ESP_GATT_PREP_WRITE_CANCEL), log the event
        ESP_LOGI(GATTS_TAG, "ESP_GATT_PREP_WRITE_CANCEL");
    }

    // After the write execution is handled, free the buffer used to store the prepared data
    if (prepare_write_env->prepare_buf) {
        free(prepare_write_env->prepare_buf);   // Free the dynamically allocated buffer to prevent memory leaks
        prepare_write_env->prepare_buf = NULL;  // Set the pointer to NULL after freeing the memory
    }

    // Reset the length of the prepared buffer to 0, indicating no more data is stored
    prepare_write_env->prepare_len = 0;  // Clear the length of the prepared buffer to reset the environment
}


//read temperature and humidity from sensor function
void dht() {

  for (int i = 0; i < 26; i++) { //source code is messed up and needs to cycle 26 times to read data

    //if read data is OK
    if(dht_read_data(DHT_TYPE_DHT11, 18, &humidity, &temperature) == ESP_OK) {
      
      humidity = humidity / 10; //modify humidity value
      temperature = ((temperature / 10) * 1.8) + 32; //convert from C to F
      printf("Humidity: %d%%, Temperature: %d F\n", humidity, temperature); //print sensor values
    }
  }
  
}

//Event handler for profile A based on the event called
static void gatts_profile_a_event_handler(esp_gatts_cb_event_t event, esp_gatt_if_t gatts_if, esp_ble_gatts_cb_param_t *param) {
    switch (event) { //Switch statement to handle different BLE GATT events
    case ESP_GATTS_REG_EVT: //Event for registering the GATT application
        ESP_LOGI(GATTS_TAG, "REGISTER_APP_EVT, status %d, app_id %d", param->reg.status, param->reg.app_id); //Log registration status and app ID
        gl_profile_tab[PROFILE_A_APP_ID].service_id.is_primary = true; //set the service to primary
        gl_profile_tab[PROFILE_A_APP_ID].service_id.id.inst_id = 0x00; //set the instance ID
        gl_profile_tab[PROFILE_A_APP_ID].service_id.id.uuid.len = ESP_UUID_LEN_16; //set UUID length
        gl_profile_tab[PROFILE_A_APP_ID].service_id.id.uuid.uuid.uuid16 = GATTS_SERVICE_UUID_A; //set UUID for the service

        //Set device name for BLE advertising
        esp_err_t set_dev_name_ret = esp_ble_gap_set_device_name(DEVICE_NAME);
        if (set_dev_name_ret){
            ESP_LOGE(GATTS_TAG, "set device name failed, error code = %x", set_dev_name_ret); //log error if setting device name fails
        }
#ifdef CONFIG_SET_RAW_ADV_DATA
        //if raw advertising data is configured
        esp_err_t raw_adv_ret = esp_ble_gap_config_adv_data_raw(raw_adv_data, sizeof(raw_adv_data));
        if (raw_adv_ret){
            ESP_LOGE(GATTS_TAG, "config raw adv data failed, error code = %x ", raw_adv_ret); //log error if configuring raw advertising data fails
        }
        adv_config_done |= adv_config_flag; //Mark advertising config as done
        esp_err_t raw_scan_ret = esp_ble_gap_config_scan_rsp_data_raw(raw_scan_rsp_data, sizeof(raw_scan_rsp_data));
        if (raw_scan_ret){
            ESP_LOGE(GATTS_TAG, "config raw scan rsp data failed, error code = %x", raw_scan_ret); //log error if configuring raw scan response data fails
        }
        adv_config_done |= scan_rsp_config_flag; //Mark scan response config as done
#else
        //Otherwise, configure standard advertisement data
        esp_err_t ret = esp_ble_gap_config_adv_data(&adv_data);
        if (ret){
            ESP_LOGE(GATTS_TAG, "config adv data failed, error code = %x", ret); //Log error if configuring advertisement data fails
        }
        adv_config_done |= adv_config_flag; //Mark advertisement config as done

        //config scan response data
        ret = esp_ble_gap_config_adv_data(&scan_rsp_data);
        if (ret){
            ESP_LOGE(GATTS_TAG, "config scan response data failed, error code = %x", ret); //Log error if configuring scan response data fails
        }
        adv_config_done |= scan_rsp_config_flag; //Mark scan response config as done

#endif
        //create GATT service
        esp_ble_gatts_create_service(gatts_if, &gl_profile_tab[PROFILE_A_APP_ID].service_id, GATTS_NUM_HANDLE_A);
        break;

    case ESP_GATTS_READ_EVT: { //event for reading a GATT characterisic
        ESP_LOGI(GATTS_TAG, "GATT_READ_EVT, conn_id %d, trans_id %" PRIu32 ", handle %d", param->read.conn_id, param->read.trans_id, param->read.handle); //log read event details
        dht(); //read sensor data (e.g., temperature and humidity)
        ESP_LOGI(GATTS_TAG, "GATT_READ_EVT, Humidity: %d%%, Temperature: %d F", humidity, temperature); //log sensor readings
        ESP_LOGI(GATTS_TAG, "GATT_READ_EVT, hello world");

        esp_gatt_rsp_t rsp;  //Create GATT response
        memset(&rsp, 0, sizeof(esp_gatt_rsp_t)); //Clear the response structure
        rsp.attr_value.handle = param->read.handle; //set response handle
        rsp.attr_value.len = 11; //Set response length
        //set response data to "hello world"
        rsp.attr_value.value[0] = 0x68;
        rsp.attr_value.value[1] = 0x65;
        rsp.attr_value.value[2] = 0x6c;
        rsp.attr_value.value[3] = 0x6c;
        rsp.attr_value.value[4] = 0x6f;
        rsp.attr_value.value[5] = 0x20;
        rsp.attr_value.value[6] = 0x77;
        rsp.attr_value.value[7] = 0x6f;
        rsp.attr_value.value[8] = 0x72;
        rsp.attr_value.value[9] = 0x6c;
        rsp.attr_value.value[10] = 0x64;

        //Send the GATT response
        esp_ble_gatts_send_response(gatts_if, param->read.conn_id, param->read.trans_id, ESP_GATT_OK, &rsp);
        break;
    }

    case ESP_GATTS_WRITE_EVT: { //Event for writing to a GATT characteristic
        ESP_LOGI(GATTS_TAG, "GATT_WRITE_EVT, conn_id %d, trans_id %" PRIu32 ", handle %d", param->write.conn_id, param->write.trans_id, param->write.handle); //log write event details
        if (!param->write.is_prep){ //If this is not a prepare write
            ESP_LOGI(GATTS_TAG, "GATT_WRITE_EVT, value len %d, value :", param->write.len); //log length of written data
            esp_log_buffer_hex(GATTS_TAG, param->write.value, param->write.len); //log written data

            //If the descriptor handle matches and data length is 2, process the write
            if (gl_profile_tab[PROFILE_A_APP_ID].descr_handle == param->write.handle && param->write.len == 2){
                uint16_t descr_value = param->write.value[1]<<8 | param->write.value[0]; //Get descriptor value from the write data

                //If descriptor value is 0x0001, enable notificaions
                if (descr_value == 0x0001){
                    if (a_property & ESP_GATT_CHAR_PROP_BIT_NOTIFY){
                        ESP_LOGI(GATTS_TAG, "notify enable");
                        uint8_t notify_data[15]; //Data to be sent in notification
                        for (int i = 0; i < sizeof(notify_data); ++i)
                        {
                            notify_data[i] = i%0xff; //Fill notification data with example patter
                        }
                        //Send the notifcation to the client
                        esp_ble_gatts_send_indicate(gatts_if, param->write.conn_id, gl_profile_tab[PROFILE_A_APP_ID].char_handle,
                                                sizeof(notify_data), notify_data, false);
                    }

                }else if (descr_value == 0x0002){ //If descriptor value is 0x0002, enable indications
                    if (a_property & ESP_GATT_CHAR_PROP_BIT_INDICATE){
                        ESP_LOGI(GATTS_TAG, "indicate enable");
                        uint8_t indicate_data[15]; //Data to be sent in indication
                        for (int i = 0; i < sizeof(indicate_data); ++i)
                        {
                            indicate_data[i] = i%0xff; //fill indication data with example pattern
                        }
                        //Send the indication to the client
                        esp_ble_gatts_send_indicate(gatts_if, param->write.conn_id, gl_profile_tab[PROFILE_A_APP_ID].char_handle,
                                                sizeof(indicate_data), indicate_data, true);
                    }
                }
                else if (descr_value == 0x0000){ //if descriptor value is 0x0000, disable notifications/indications
                    ESP_LOGI(GATTS_TAG, "notify/indicate disable ");
                }else{
                    ESP_LOGE(GATTS_TAG, "unknown descr value");
                    esp_log_buffer_hex(GATTS_TAG, param->write.value, param->write.len); //log unknown descriptor value
                }

            }
        }
        example_write_event_env(gatts_if, &a_prepare_write_env, param); //Handle the write event
        break;
    }

    case ESP_GATTS_EXEC_WRITE_EVT: //Event for executing a write operation
        ESP_LOGI(GATTS_TAG,"ESP_GATTS_EXEC_WRITE_EVT");
        esp_ble_gatts_send_response(gatts_if, param->write.conn_id, param->write.trans_id, ESP_GATT_OK, NULL); //Send success response
        example_exec_write_event_env(&a_prepare_write_env, param); //Handle the executed write
        break;

    case ESP_GATTS_MTU_EVT: //Event for MTU size change
        ESP_LOGI(GATTS_TAG, "ESP_GATTS_MTU_EVT, MTU %d", param->mtu.mtu); //log the new MTU size
        break;

    case ESP_GATTS_UNREG_EVT: //Event for unregistration
        break;

    case ESP_GATTS_CREATE_EVT: //Event for service creation
        ESP_LOGI(GATTS_TAG, "CREATE_SERVICE_EVT, status %d,  service_handle %d", param->create.status, param->create.service_handle); //log service creation details
        gl_profile_tab[PROFILE_A_APP_ID].service_handle = param->create.service_handle; //Store the service handle
        gl_profile_tab[PROFILE_A_APP_ID].char_uuid.len = ESP_UUID_LEN_16; //set length of characteristic UUID to 16 bytes
        gl_profile_tab[PROFILE_A_APP_ID].char_uuid.uuid.uuid16 = GATTS_CHAR_UUID_A; //set the 16-bit UUID for the characteristic

        //Start the service using the stored service handle
        esp_ble_gatts_start_service(gl_profile_tab[PROFILE_A_APP_ID].service_handle);

        //Define the properties of the characteristic (read, write, notify)
        a_property = ESP_GATT_CHAR_PROP_BIT_READ | ESP_GATT_CHAR_PROP_BIT_WRITE | ESP_GATT_CHAR_PROP_BIT_NOTIFY;

        //Add the characteristic to the service with specified permissions and properties
        esp_err_t add_char_ret = esp_ble_gatts_add_char(gl_profile_tab[PROFILE_A_APP_ID].service_handle, &gl_profile_tab[PROFILE_A_APP_ID].char_uuid,
                                                        ESP_GATT_PERM_READ | ESP_GATT_PERM_WRITE,
                                                        a_property,
                                                        &gatts_char1_val, NULL);

        if (add_char_ret){ //if the characteristic was added successfully
            ESP_LOGE(GATTS_TAG, "add char failed, error code =%x",add_char_ret); //log error if adding the characteristic failed
        }
        break;

    case ESP_GATTS_ADD_INCL_SRVC_EVT: //event for including a service
        break;

    case ESP_GATTS_ADD_CHAR_EVT: { //event triggered after adding a characteristic
        uint16_t length = 0;
        const uint8_t *prf_char;

        //log the status, attribute handle, and service handle of the added characteristic
        ESP_LOGI(GATTS_TAG, "ADD_CHAR_EVT, status %d,  attr_handle %d, service_handle %d", 
                param->add_char.status, param->add_char.attr_handle, param->add_char.service_handle);

        gl_profile_tab[PROFILE_A_APP_ID].char_handle = param->add_char.attr_handle; //Store the attribute handle of the added characteristic
        gl_profile_tab[PROFILE_A_APP_ID].descr_uuid.len = ESP_UUID_LEN_16; //set the length of the descriptor UUID to 16 bytes
        gl_profile_tab[PROFILE_A_APP_ID].descr_uuid.uuid.uuid16 = ESP_GATT_UUID_CHAR_CLIENT_CONFIG; //Set the UUID of the characteristic descriptor to the client configuration descriptor UUID

        //retrieve the value of the characteristic attribute
        esp_err_t get_attr_ret = esp_ble_gatts_get_attr_value(param->add_char.attr_handle,  &length, &prf_char);

        if (get_attr_ret == ESP_FAIL){ 
            ESP_LOGE(GATTS_TAG, "ILLEGAL HANDLE"); //log error if retrieving attribute value fails
        }
        ESP_LOGI(GATTS_TAG, "the gatts char length = %x", length); //log length of the characteristic value

        for(int i = 0; i < length; i++){ //log the individual bytes of the characteristic value
            ESP_LOGI(GATTS_TAG, "prf_char[%x] =%x",i,prf_char[i]);
        }

        //add the characteristic descriptor to the service
        esp_err_t add_descr_ret = esp_ble_gatts_add_char_descr(gl_profile_tab[PROFILE_A_APP_ID].service_handle, &gl_profile_tab[PROFILE_A_APP_ID].descr_uuid,
                                                                ESP_GATT_PERM_READ | ESP_GATT_PERM_WRITE, NULL, NULL);


        if (add_descr_ret){ //check if adding the descriptor was successful
            ESP_LOGE(GATTS_TAG, "add char descr failed, error code =%x", add_descr_ret); //log error if adding the descriptor fails
        }
        break;   
    }

    case ESP_GATTS_ADD_CHAR_DESCR_EVT: //Event triggered after adding a descriptor to the characteristic
        gl_profile_tab[PROFILE_A_APP_ID].descr_handle = param->add_char_descr.attr_handle; //store the handle of the added descriptor
        
        //log the status, attribute handle, and service handle of the added descriptor
        ESP_LOGI(GATTS_TAG, "ADD_DESCR_EVT, status %d, attr_handle %d, service_handle %d",
                 param->add_char_descr.status, param->add_char_descr.attr_handle, param->add_char_descr.service_handle);
        break;

    case ESP_GATTS_DELETE_EVT: //event for deleting a service or characteristic
        break;

    case ESP_GATTS_START_EVT: //log the status and handle of the started service
        ESP_LOGI(GATTS_TAG, "SERVICE_START_EVT, status %d, service_handle %d",
                 param->start.status, param->start.service_handle);
        break;

    case ESP_GATTS_STOP_EVT: //event for stopping the service
        break;

    case ESP_GATTS_CONNECT_EVT: { //event triggered when a client connects to the server

        //initialize a structure for connection parameters
        esp_ble_conn_update_params_t conn_params = {0};

        //copy the remote device address into the connection parameters
        memcpy(conn_params.bda, param->connect.remote_bda, sizeof(esp_bd_addr_t));
        
        conn_params.latency = 0; //set the latency to 0 (no additional delay)
        conn_params.max_int = 0x20;    // max_int = 0x20*1.25ms = 40ms
        conn_params.min_int = 0x10;    // min_int = 0x10*1.25ms = 20ms
        conn_params.timeout = 400;    // timeout = 400*10ms = 4000ms

        //log connection details such as connection ID and remote device address
        ESP_LOGI(GATTS_TAG, "ESP_GATTS_CONNECT_EVT, conn_id %d, remote %02x:%02x:%02x:%02x:%02x:%02x:",
                 param->connect.conn_id,
                 param->connect.remote_bda[0], param->connect.remote_bda[1], param->connect.remote_bda[2],
                 param->connect.remote_bda[3], param->connect.remote_bda[4], param->connect.remote_bda[5]);
        
        gl_profile_tab[PROFILE_A_APP_ID].conn_id = param->connect.conn_id; //store the connection ID in the global profile
        //send the update connection parameters to the peer device.
        esp_ble_gap_update_conn_params(&conn_params);
        break;
    }

    case ESP_GATTS_DISCONNECT_EVT: //log the reason for the disconnection
        ESP_LOGI(GATTS_TAG, "ESP_GATTS_DISCONNECT_EVT, disconnect reason 0x%x", param->disconnect.reason);
        esp_ble_gap_start_advertising(&adv_params); //restart advertising to allow new connection
        break;

    case ESP_GATTS_CONF_EVT: //log the status and handle of the confirmation event
        ESP_LOGI(GATTS_TAG, "ESP_GATTS_CONF_EVT, status %d attr_handle %d", param->conf.status, param->conf.handle);

        if (param->conf.status != ESP_GATT_OK){ //if the status is not OK, log the response value
            esp_log_buffer_hex(GATTS_TAG, param->conf.value, param->conf.len);
        }
        break;

    case ESP_GATTS_OPEN_EVT:
    case ESP_GATTS_CANCEL_OPEN_EVT:
    case ESP_GATTS_CLOSE_EVT:
    case ESP_GATTS_LISTEN_EVT:
    case ESP_GATTS_CONGEST_EVT:
    default: //Default case for unhandled events
        break;
    }
}

// GATTS event handler
static void gatts_event_handler(esp_gatts_cb_event_t event, esp_gatt_if_t gatts_if, esp_ble_gatts_cb_param_t *param)
{
    // If the event is a registration event (ESP_GATTS_REG_EVT), store the gatts_if for each profile
    if (event == ESP_GATTS_REG_EVT) {
        if (param->reg.status == ESP_GATT_OK) {
            // If registration was successful, store the GATT interface for this profile
            gl_profile_tab[param->reg.app_id].gatts_if = gatts_if;
        } 
        else { // If registration failed, log an error and return
            ESP_LOGI(GATTS_TAG, "Reg app failed, app_id %04x, status %d", param->reg.app_id, param->reg.status);
            return; // Return from the function if registration failed
        }
    }

    /* 
    If the gatts_if (GATT interface) is equal to profile A's GATT interface,
    call the corresponding callback function for that profile.
    The loop ensures that the callback for each profile is called as needed.
    */
    do {
        int idx;
        // Iterate over all profiles (up to PROFILE_NUM profiles)
        for (idx = 0; idx < PROFILE_NUM; idx++) {
            // Check if gatts_if is ESP_GATT_IF_NONE (meaning all profiles should be called) or matches the current profile's GATT interface
            if (gatts_if == ESP_GATT_IF_NONE || 
                gatts_if == gl_profile_tab[idx].gatts_if) {
                // If a callback function exists for this profile, call it with the event, gatts_if, and event parameters
                if (gl_profile_tab[idx].gatts_cb) {
                    gl_profile_tab[idx].gatts_cb(event, gatts_if, param);
                }
            }
        }
    } while (0); // The loop is written as a do-while(0) to ensure the code is executed once and the block structure remains intact
}

/******************************************************************************************************************************************* */

/*************Start of GPIO Interrupts****************/

//Global variables for GPIO Interrupts
uint32_t duty;
uint32_t duty_speaker;
uint32_t volume;
uint32_t brightness;
int on_off_LED;
int on_off_speaker;
static TimerHandle_t debounce_timer;
static bool gpio_event_pending = false;

//Configure the LEDC
void ledc_configuration() {
  //First configure the timer of the LEDC
  ledc_timer_config_t ledc_timer = {
    .speed_mode      = LEDC_LOW_SPEED_MODE,
    .timer_num       = LEDC_TIMER_0,
    .duty_resolution = LEDC_TIMER_13_BIT,
    .freq_hz         = 2000,
    .clk_cfg         = LEDC_AUTO_CLK
  };
  ledc_timer_config(&ledc_timer);

  //Second configure the channel of the LEDC
  ledc_channel_config_t ledc_channel = {
    .speed_mode = LEDC_LOW_SPEED_MODE,
    .channel    = LEDC_CHANNEL_0,
    .timer_sel  = LEDC_TIMER_0,
    .intr_type  = LEDC_INTR_DISABLE,
    .gpio_num   = 22,
    .duty       = duty,
    .hpoint     = 0
  };
  ledc_channel_config(&ledc_channel);
}

//Configure the speaker control
void speaker_configuration() {
  //First configure the timer of the speaker control
  ledc_timer_config_t speaker_timer = {
    .speed_mode      = LEDC_LOW_SPEED_MODE,
    .timer_num       = LEDC_TIMER_1,
    .duty_resolution = LEDC_TIMER_12_BIT,
    .freq_hz         = 100,
    .clk_cfg         = LEDC_AUTO_CLK
  };
  ledc_timer_config(&speaker_timer);

  //Second configure the channel of the speaker control
  ledc_channel_config_t speaker_channel = {
    .speed_mode = LEDC_LOW_SPEED_MODE,
    .channel    = LEDC_CHANNEL_1,
    .timer_sel  = LEDC_TIMER_1,
    .intr_type  = LEDC_INTR_DISABLE,
    .gpio_num   = 19,
    .duty       = duty_speaker,
    .hpoint     = 0
  };
  ledc_channel_config(&speaker_channel);
}

//brightness up interrupt
static void IRAM_ATTR brightness_up(void* arg) {

    if (duty < 8000 && on_off_LED == 1 && !gpio_event_pending) {
        duty += 2000; //adjust duty cycle
        brightness += 25;

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
        //printf(" duty -> %ld\n", duty);
        //printf("Brightness Percent  -> %ld\n", brightness);
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //udate duty cycle

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
}

//brightness down interrupt
static void IRAM_ATTR brightness_down(void* arg) {

    if (duty > 2000 && duty <= 8000 && on_off_LED == 1 && !gpio_event_pending) {
        duty -= 2000; //adjust duty cycle
        brightness -= 25;

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
        //printf(" duty -> %ld\n", duty);
        //printf("Brightness Percent  -> %ld\n", brightness);
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
}

//light off interrupt
static void IRAM_ATTR light_off(void* arg) {

  ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, 0); //set duty cycle
  //printf(" light is off.\n");
  //printf(" duty -> %ld\n", duty);
  //printf("Brightness Percent  -> %ld\n", brightness);
  ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle
  on_off_LED = 0;
}

//light on interrupt
static void IRAM_ATTR light_on(void* arg) {

  //duty = 0 set it to 8000
  if(duty == 0)
  {
    duty = 8000;
    brightness = 100;
  }

  ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
  //printf(" light is on.\n");
  //printf(" duty -> %ld\n", duty);
  //printf("Brightness Percent  -> %ld\n", brightness);
  ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle
  on_off_LED = 1;
}

//volume up interrupt
static void IRAM_ATTR volume_up(void* arg) {

    if (duty_speaker < 100 && on_off_speaker == 1 && !gpio_event_pending) {

        duty_speaker += 10; //adjust duty cycle
        volume += 10;

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
        //printf(" Speaker Volume -> %ld\n", duty_speaker);
        //printf("Volume Percent  -> %ld\n", volume);
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
}

//volume down interrupt
static void IRAM_ATTR volume_down(void* arg) {

    if (duty_speaker > 10 && duty_speaker <= 100 && on_off_speaker == 1 && !gpio_event_pending) {

        duty_speaker -= 10; //adjust duty cycle
        volume -= 10; 

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
        //printf(" Speaker duty -> %ld\n", duty_speaker);
        //printf("Volume Percent  -> %ld\n", volume);
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
}

//speaker off interrupt
static void IRAM_ATTR speaker_off(void* arg) {

    ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, 0); //set duty cycle
    //printf(" Speaker is off.\n");
    //printf(" Speaker volume -> %ld\n", duty_speaker);
    //printf("Volume Percent  -> %ld\n", volume);
    ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle
    on_off_speaker = 0;
}

//speaker on interrupt
static void IRAM_ATTR speaker_on(void* arg) {

    //if duty_speaker is 0 then set to 100
    if (duty_speaker == 0)
    {
        duty_speaker = 100;
        volume = 100;
    }

    ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
    //printf(" Speaker is on.\n");
    //printf(" Speaker volume -> %ld\n", duty_speaker);
    //printf("Volume Percent  -> %ld\n", volume);
    ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle
    on_off_speaker = 1;
}

//Timer for in between interrupts
void debounce_timer_callback(TimerHandle_t xTimer) {
    gpio_event_pending = false;
}

void gpio_configuration() {

    //Configure inputs
    gpio_set_direction(GPIO_NUM_25, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_26, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_32, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_33, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_27, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_14, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_13, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_23, GPIO_MODE_INPUT);

    //set all input gpios to pulldown mode
    gpio_set_pull_mode(GPIO_NUM_25, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_26, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_32, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_33, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_27, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_14, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_13, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_23, GPIO_PULLDOWN_ONLY);

    //install interrupt service
    gpio_install_isr_service(ESP_INTR_FLAG_LEVEL3);

    //add light on interrupt D23
    gpio_isr_handler_add(GPIO_NUM_23, light_on, NULL);
    gpio_set_intr_type(GPIO_NUM_23, GPIO_INTR_POSEDGE);

    //add light off interrupt D32
    gpio_isr_handler_add(GPIO_NUM_32, light_off, NULL);
    gpio_set_intr_type(GPIO_NUM_32, GPIO_INTR_POSEDGE);

    //add brightness up interrupt D33
    gpio_isr_handler_add(GPIO_NUM_33, brightness_up, NULL);
    gpio_set_intr_type(GPIO_NUM_33, GPIO_INTR_POSEDGE);

    //add brightness down interrupt D25
    gpio_isr_handler_add(GPIO_NUM_25, brightness_down, NULL);
    gpio_set_intr_type(GPIO_NUM_25, GPIO_INTR_POSEDGE);

    //add speaker on interrupt D26
    gpio_isr_handler_add(GPIO_NUM_26, speaker_on, NULL);
    gpio_set_intr_type(GPIO_NUM_26, GPIO_INTR_POSEDGE);

    //add speaker off interrupt D27
    gpio_isr_handler_add(GPIO_NUM_27, speaker_off, NULL);
    gpio_set_intr_type(GPIO_NUM_27, GPIO_INTR_POSEDGE);

    //add volume up interrupt D14
    gpio_isr_handler_add(GPIO_NUM_14, volume_up, NULL);
    gpio_set_intr_type(GPIO_NUM_14, GPIO_INTR_POSEDGE);

    //add volume down interrupt D13
    gpio_isr_handler_add(GPIO_NUM_13, volume_down, NULL);
    gpio_set_intr_type(GPIO_NUM_13, GPIO_INTR_POSEDGE);

}

/**********************MAIN**********************/

void app_main(void)
{
    //Call temperature sensor function to obtain data
    dht();

    //initialize error value
    esp_err_t ret;

    // Initialize NVS.
    ret = nvs_flash_init();
    if (ret == ESP_ERR_NVS_NO_FREE_PAGES || ret == ESP_ERR_NVS_NEW_VERSION_FOUND) {
        ESP_ERROR_CHECK(nvs_flash_erase()); //Check Error
        ret = nvs_flash_init(); //retry initialization
    }
    ESP_ERROR_CHECK( ret );

    ESP_ERROR_CHECK(esp_bt_controller_mem_release(ESP_BT_MODE_CLASSIC_BT));

    //Bluetooth controller configuration
    esp_bt_controller_config_t bt_cfg = BT_CONTROLLER_INIT_CONFIG_DEFAULT();

    //initialize Bluetooth controller with error check
    ret = esp_bt_controller_init(&bt_cfg);
    if (ret) {
        ESP_LOGE(GATTS_TAG, "%s initialize controller failed: %s", __func__, esp_err_to_name(ret));
        return;
    }

    //enable Bluetooth controller with error check
    ret = esp_bt_controller_enable(ESP_BT_MODE_BLE);
    if (ret) {
        ESP_LOGE(GATTS_TAG, "%s enable controller failed: %s", __func__, esp_err_to_name(ret));
        return;
    }

    //initialize bluedroid with error check
    ret = esp_bluedroid_init();
    if (ret) {
        ESP_LOGE(GATTS_TAG, "%s init bluetooth failed: %s", __func__, esp_err_to_name(ret));
        return;
    }

    //enable bluedroid with error check
    ret = esp_bluedroid_enable();
    if (ret) {
        ESP_LOGE(GATTS_TAG, "%s enable bluetooth failed: %s", __func__, esp_err_to_name(ret));
        return;
    }

    //Check status of gatts register with error check
    ret = esp_ble_gatts_register_callback(gatts_event_handler);
    if (ret){
        ESP_LOGE(GATTS_TAG, "gatts register error, error code = %x", ret);
        return;
    }

    //Check status of gap register with error check
    ret = esp_ble_gap_register_callback(gap_event_handler);
    if (ret){
        ESP_LOGE(GATTS_TAG, "gap register error, error code = %x", ret);
        return;
    }

    //check status of gatts profile app register with error check
    ret = esp_ble_gatts_app_register(PROFILE_A_APP_ID);
    if (ret){
        ESP_LOGE(GATTS_TAG, "gatts app register error, error code = %x", ret);
        return;
    }
    
    //Set local MTU with error check
    esp_err_t local_mtu_ret = esp_ble_gatt_set_local_mtu(500);
    if (local_mtu_ret){
        ESP_LOGE(GATTS_TAG, "set local  MTU failed, error code = %x", local_mtu_ret);
    }

    //Call LEDC configuration
    ledc_configuration();

    //Call Speaker Configuration
    speaker_configuration();

    //Call GPIO configuration
    gpio_configuration();

    //Create task for interrupt timer
    debounce_timer = xTimerCreate("debounce _timer", pdMS_TO_TICKS(1000), pdFALSE, NULL, debounce_timer_callback);

    return;
}