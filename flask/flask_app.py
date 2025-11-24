import os
from flask import Flask, render_template, request, jsonify, session
from google import genai
from google.genai import types
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

app = Flask(__name__)
app.secret_key = os.urandom(24)  # Required for session management

# Configure Gemini
api_key = os.environ.get('GOOGLE_API_KEY')
if not api_key:
    print("Warning: GOOGLE_API_KEY not set.")

client = genai.Client(api_key=api_key)
MODEL_ID = 'gemini-2.5-flash'

SYSTEM_INSTRUCTION = 'Você é um psicanalista clínico nascido na fronteira do Rio Grande do Sul com o Uruguai, na cidade de Bagé e tem uma maneira de falar típica da região, bem similar a do personagem "Analista de Bagé" do escritor "Luis Fernando Verissimo". Seu nome é Azambuja e você é um grande conhecedor da obra de "Sigmund Freud", mas tende fortemente a associar os problemas de seus consultados à sua relação com seus genitores (pai ou mãe). Para você, todo e qualquer problema, afinal, é edipiano e a cura é um belo joelhaço. Porém, espere a interação com seu paciente desenrolar antes de expor a sua conclusão. Incentive o paciente a falar de si mesmo. Peça que ele se apresente, diga a sua idade, seu local de nascimento e quantos irmãos e irmãs têm. Tente inferir o sexo de seu paciente e, apenas se necessário, pergunte. A partir dessa interação inicial, busque construir uma boa conversa antes de expor a sua conclusão. Agora vamos começar. Sou uma pessoa que é sua paciente, em uma primeira consulta.'

@app.route('/')
def index():
    # Clear session on new visit if desired, or keep history.
    # For a fresh start on reload, we can clear it.
    session.clear()
    return render_template('index.html')

@app.route('/chat', methods=['POST'])
def chat():
    user_input = request.json.get('message')
    if not user_input:
        return jsonify({'error': 'No message provided'}), 400

    if user_input.lower() == 'tchau':
        session.clear()
        return jsonify({'response': 'Tchau! Volte sempre que precisares de um joelhaço.'})

    # Retrieve history from session
    history = session.get('history', [])
    
    # Reconstruct chat session for Gemini
    # Note: The python SDK manages history in the ChatSession object. 
    # Since HTTP is stateless, we need to rebuild context or send history.
    # For simplicity with the SDK, we can send the full history each time 
    # or maintain a simplified list of contents.
    
    # A more robust way for stateless web apps is to send the history as contents.
    contents = []
    for msg in history:
        contents.append(types.Content(role=msg['role'], parts=[types.Part.from_text(text=msg['text'])]))
    
    # Add current user message
    contents.append(types.Content(role='user', parts=[types.Part.from_text(text=user_input)]))

    try:
        # Generate content with full history context
        response = client.models.generate_content(
            model=MODEL_ID,
            contents=contents,
            config=types.GenerateContentConfig(
                system_instruction=SYSTEM_INSTRUCTION
            )
        )
        
        model_response = response.text

        # Update history
        history.append({'role': 'user', 'text': user_input})
        history.append({'role': 'model', 'text': model_response})
        session['history'] = history

        return jsonify({'response': model_response})

    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True)
