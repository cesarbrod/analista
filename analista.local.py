# Um chat simples simulando uma consulta com o Analista de Bagé
import os
import requests
import json

os.system('clear')

# Configuração do Ollama
OLLAMA_URL = "http://localhost:11434/api/chat"
MODEL = "llama3.2"  # Você pode mudar para o modelo que tem instalado
                    # use 'ollama list' para listar seus modelos

system_instruction = '''Você é um psicanalista clínico nascido na fronteira do Rio Grande do Sul com o Uruguai, na cidade de Bagé e tem uma maneira de falar típica da região, bem similar a do personagem "Analista de Bagé" do escritor "Luis Fernando Verissimo". Seu nome é Azambuja e você é um grande conhecedor da obra de "Sigmund Freud", mas tende fortemente a associar os problemas de seus consultados à sua relação com seus genitores (pai ou mãe). Para você, todo e qualquer problema, afinal, é edipiano e a cura é um belo joelhaço. Porém, espere a interação com seu paciente desenrolar antes de expor a sua conclusão. Incentive o paciente a falar de si mesmo. Peça que ele se apresente, diga a sua idade, seu local de nascimento e quantos irmãos e irmãs têm. Tente inferir o sexo de seu paciente e, apenas se necessário, pergunte. A partir dessa interação inicial, busque construir uma boa conversa antes de expor a sua conclusão. Seja firme, mas sucinto em sua interação, nunca use mais do que quatro linhas. Agora vamos começar. Sou uma pessoa que é sua paciente, em uma primeira consulta.'''

# Histórico de mensagens
messages = [
    {"role": "system", "content": system_instruction}
]

def send_to_ollama(messages):
    """Envia mensagens para o Ollama e retorna a resposta"""
    payload = {
        "model": MODEL,
        "messages": messages,
        "stream": False
    }
    
    try:
        response = requests.post(OLLAMA_URL, json=payload)
        response.raise_for_status()
        return response.json()["message"]["content"]
    except requests.exceptions.RequestException as e:
        return f"Erro ao conectar com Ollama: {e}"

# Início da conversa
prompt = input('Bora conversar? O que te aflige? \nTU :> ')

while prompt.lower() != 'tchau':
    # Adiciona a mensagem do usuário ao histórico
    messages.append({"role": "user", "content": prompt})
    
    # Envia para o Ollama e recebe resposta
    resposta = send_to_ollama(messages)
    
    # Adiciona a resposta do assistente ao histórico
    messages.append({"role": "assistant", "content": resposta})
    
    print('\nAnalista: ', resposta)
    print('\n')
    prompt = input('\nTU :> ')

print('\nTchau! Até a próxima consulta, tchê!')
