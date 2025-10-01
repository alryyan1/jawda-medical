const net = require('net');

// Test script to verify HL7 server connection
const HL7_SERVER_HOST = '127.0.0.1';
const HL7_SERVER_PORT = 6400;

const testMessage = `MSH|^~\\&|LAB|HOSPITAL|LIS|CLINIC|20240101120000||ORU^R01^ORU_R01|12345|P|2.5.1
PID|1||123456789^^^MRN^MR||DOE^JOHN^MIDDLE||19900101|M|||123 MAIN ST^^CITY^ST^12345||555-1234|||S||123456789
PV1|1|I|ICU^101^01|||DOC123^SMITH^JANE^MD|||||||||1|||||||||||||||||||||||||20240101120000
OBR|1|12345^LAB|LAB123^CBC^L|||20240101120000|||||||||DOC123^SMITH^JANE^MD|||||20240101120000|||F||||||
OBX|1|NM|WBC^White Blood Cell Count^LN||7.5|10*3/uL|4.0-11.0|N|||F|||20240101120000
OBX|2|NM|RBC^Red Blood Cell Count^LN||4.5|10*6/uL|4.0-5.5|N|||F|||20240101120000
OBX|3|NM|HGB^Hemoglobin^LN||14.0|g/dL|12.0-16.0|N|||F|||20240101120000`;

console.log('Testing HL7 server connection...');
console.log(`Connecting to ${HL7_SERVER_HOST}:${HL7_SERVER_PORT}`);

const client = new net.Socket();
let responseData = '';

client.connect(HL7_SERVER_PORT, HL7_SERVER_HOST, () => {
  console.log('✅ Connected to HL7 server');
  console.log('📤 Sending test message...');
  client.write(testMessage);
});

client.on('data', (data) => {
  responseData += data.toString();
  console.log('📥 Received response:', data.toString());
});

client.on('close', () => {
  console.log('🔌 Connection closed');
  console.log('📋 Full response:', responseData);
  process.exit(0);
});

client.on('error', (error) => {
  console.error('❌ Connection error:', error.message);
  process.exit(1);
});

// Timeout after 10 seconds
setTimeout(() => {
  console.log('⏰ Connection timeout');
  client.destroy();
  process.exit(1);
}, 10000);
