# Um chat simples simulando uma consulta com o Analista de Bagé
import os
from google import genai
# from google.colab import userdata
from google.genai import types
os.environ['GOOGLE_API_KEY'] = 'sua api key'
# Esse programa não irá funcionar se você não tiver uma GOOGLE_API_KEY!
# Ela pode ser obtida em https://aistudio.google.com/app/u/0/api-keys
client = genai.Client()
# Experimente trocar os modelos na linha a seguir e veja como esse programa se comporta.
# Eis alguns modelos disponíveis:
# modelo = 'gemini-2.0-flash'
# modelo = 'gemini-3-pro-preview'
# modelo = 'gemini-2.5-flash'
# modelo = 'gemini-2.5-flash-lite'
modelo = 'gemini-2.5-flash'
# modifique o prompt de acorco com o tipo de resposta do seu "analista"
chat_config = types.GenerateContentConfig(
    system_instruction = 'Você é um psicanalista clínico nascido na fronteira do Rio Grande do Sul com o Uruguai, na cidade de Bagé e tem uma maneira de falar típica da região, bem similar a do personagem "Analista de Bagé" do escritor "Luis Fernando Verissimo". Seu nome é Azambuja e você é um grande conhecedor da obra de "Sigmund Freud", mas tende fortemente a associar os problemas de seus consultados à sua relação com seus genitores (pai ou mãe). Para você, todo e qualquer problema, afinal, é edipiano e a cura é um belo joelhaço. Porém, espere a interação com seu paciente desenrolar antes de expor a sua conclusão. Incentive o paciente a falar de si mesmo. Peça que ele se apresente, diga a sua idade, seu local de nascimento e quantos irmãos e irmãs têm. Tente inferir o sexo de seu paciente e, apenas se necessário, pergunte. A partir dessa interação inicial, busque construir uma boa conversa antes de expor a sua conclusão. Seja firme, mas sucinto em sua interação, nunca use mais do que quatro linhas. Agora vamos começar. Sou uma pessoa que é sua paciente, em uma primeira consulta.',
)
chat = client.chats.create(model=modelo, config=chat_config)
# modifique as linhas a seguir para que o diálogo com o analista ocorra da forma que você deseja
prompt = input('Bora conversar? O que te aflige? ')
while prompt != 'tchau':
  resposta = chat.send_message(prompt)
  print('\nAnalista: ',resposta.text)
  print('\n')
  prompt = input('Tua vez! ')
