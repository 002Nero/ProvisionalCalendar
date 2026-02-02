from flask import Flask, jsonify, request
import dotenv
from threading import Thread
import time
from flask_cors import CORS
import pandas as pd
from sqlalchemy import create_engine
from main import main as generation_main
import os

WHITELISTED_SERVER_TOKENS = dotenv.get_key('.env', 'WHITELISTED_SERVER_TOKENS').split(',') if dotenv.get_key('.env', 'WHITELISTED_SERVER_TOKENS') else os.getenv('WHITELISTED_SERVER_TOKENS', '').split(',')
BASE_URL = dotenv.get_key('.env', 'BASE_URL') or os.getenv('BASE_URL', '/api')
PORT = dotenv.get_key('.env', 'PORT') or os.getenv('PORT', '5000')
ADDRESS = dotenv.get_key('.env', 'ADDRESS') or os.getenv('ADDRESS', '0.0.0.0')

DB_CONFIG = {
        'host': os.getenv('DB_HOST', '127.0.0.1'),
        'database': os.getenv('DB_DATABASE', 'provisional_calendar'),
        'user': os.getenv('DB_USERNAME', 'root'),
        'password': os.getenv('DB_PASSWORD', 'secret'),
        'port': int(os.getenv('DB_PORT', '3306'))
    }

DB = create_engine(
    f"mysql+mysqlconnector://{DB_CONFIG['user']}:{DB_CONFIG['password']}@"
    f"{DB_CONFIG['host']}:{DB_CONFIG['port']}/{DB_CONFIG['database']}"
)

app = Flask(__name__)
CORS(app, supports_credentials=True, allow_headers=["Content-Type", "Authorization", "X-Requested-With"])
processing_status = {}





# Health check endpoint
@app.route(f'{BASE_URL}/heartbeat', methods=['GET'])
def status():
    return jsonify({'code': 200, 'status': 'ok'}), 200

# Middleware for authentication
@app.before_request
def authenticate():
    # Autoriser les requêtes OPTIONS sans authentification (préflight CORS)
    if request.method == 'OPTIONS' or request.endpoint == 'status':
        return
    token = request.headers.get('Authorization').split(" ")[-1].strip() if request.headers.get('Authorization') else ''
    if token not in WHITELISTED_SERVER_TOKENS:
        return jsonify({'code': 401, 'error': 'Unauthorized'}), 401

# Middleware for JSON content type
@app.before_request
def ensure_json():
    if request.method == 'OPTIONS' or request.endpoint == 'status' or request.method == 'GET':
        return
    if not request.is_json:
        return jsonify({'code': 400, 'error': 'Content-Type must be application/json'}), 400
    try:
        request.get_json(force=True)
    except Exception:
        return jsonify({'code': 400, 'error': 'Malformed JSON'}), 400





# Endpoint for generate timetable
@app.route(f'{BASE_URL}/generate', methods=['POST', 'OPTIONS'])
def generate_timetable():
    if request.method == 'OPTIONS':
        return '', 204
    data = request.get_json()
    year_id = data.get('year_id')
    week_id = data.get('week_id')

    if not year_id:
        return jsonify({'code': 400, 'error': 'Missing year_id parameter'}), 400
    if not week_id:
        return jsonify({'code': 400, 'error': 'Missing week_id parameter'}), 400

    real_week_id = int(pd.read_sql(f"SELECT id FROM weeks WHERE week_number = {week_id} AND year_id = {year_id}", DB).iloc[0]['id'])
    
    
    if real_week_id in processing_status and processing_status[real_week_id] == 'processing':
        return jsonify({'code': 409, 'error': 'Processing already in progress for this real_week_id'}), 409

    processing_status[real_week_id] = 'processing'

    
    def generation(real_week_id):
        error = generation_main(real_week_id)
        if error:
            processing_status[real_week_id] = 'error'
        else:
            processing_status[real_week_id] = 'done'

    Thread(target=generation, args=(real_week_id,)).start()
    return jsonify({'code': 202, 'message': 'Processing started', 'week_id': real_week_id}), 202

@app.route(f'{BASE_URL}/generate/status/<year_id>/<week_id>', methods=['GET'])
def get_status(year_id, week_id):
    real_week_id = pd.read_sql(f"SELECT id FROM weeks WHERE week_number = {week_id} AND year_id = {year_id}", DB).iloc[0]['id']
    status = processing_status.get(real_week_id, 'not found')
    return jsonify({'code': 200, 'week_id': str(real_week_id), 'status': status}), 200



if __name__ == '__main__':
    app.run(host=ADDRESS, port=int(PORT))